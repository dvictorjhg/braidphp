<?php

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request extends ServerRequestState implements ServerRequestInterface
{
    private string $method;
    private Uri $uri;
    private ?string $requestTarget = null;

    /**
     * Initializes a new HTTP request instance.
     *
     * If the provided parameters are null, this constructor will attempt to infer values from the server environment.
     *
     * @param string|null $protocolVersion The HTTP protocol version.
     *                                     Defaults to the server protocol version if available, or '1.1'.
     * @param HttpMethod|string|null $method The HTTP method (e.g., GET, POST).
     *                                       Defaults to the server request method if available.
     * @param UriInterface|string|null $uri The URI associated with the request.
     *                                      Defaults to the server request URI if available.
     * @param array<string, string|array<int, string>> $headers An associative array of HTTP headers.
     *                              Defaults to the headers retrieved from the server environment.
     * @param bool|float|int|object|resource|StreamInterface|string|null $body The body of the request.
     *                                                                         Defaults to reading from 'php://input'.
        * @param array<string|int, mixed>|null $serverParams Explicit server parameters; null uses PHP globals.
     *
     * @throws \InvalidArgumentException If any of the inputs are invalid or cannot be processed.
     * @throws RequestException If the input stream (`php://input`) cannot be opened.
     */
    public function __construct(
        ?string $protocolVersion = null,
        HttpMethod|string|null $method = null,
        UriInterface|string|null $uri = null,
        array $headers = [],
        mixed $body = null,
        ?array $serverParams = null
    ) {
        $this->initializeServerParams($serverParams);
        $serverProtocol = $this->serverParams['SERVER_PROTOCOL'] ?? null;
        $protocolVersion ??= \is_string($serverProtocol) && \str_contains($serverProtocol, '/')
            ? \explode('/', $serverProtocol, 2)[1]
            : '1.1';

        $serverMethod = $this->serverParams['REQUEST_METHOD'] ?? null;
        $method ??= \is_string($serverMethod) ? $serverMethod : 'GET';

        $serverUri = $this->serverParams['REQUEST_URI'] ?? null;
        $uri ??= \is_string($serverUri) ? $serverUri : '/';

        if ($headers === [] && $serverParams === null) {
            $headers = self::getHttpHeaders();
        }

        if ($body === null) {
            $resource = @\fopen('php://input', 'r');
            if ($resource === false) {
                throw new RequestException('Failed to open input stream from php://input');
            }
            $body = new Stream($resource);
        }

        $this->method = $this->filterMethod(($method instanceof HttpMethod) ? $method->name : $method);

        $this->uri = $uri instanceof Uri ? $uri : new Uri($uri);

        parent::__construct($headers, $body, $protocolVersion);

        $this->initializeParsedBody(
            method: $this->method,
            useGlobals: $serverParams === null
        );

        if (!$this->hasHeader('Host')) {
            $this->setHostHeaderFromUri();
        }

        \parse_str($this->uri->getQuery(), $this->queryParams);
    }

    /**
     * Validates the request-target value.
     * @link https://www.rfc-editor.org/rfc/rfc9112#section-3.2 (for the various
     *     request-target forms allowed in request messages)
     * @param string $requestTarget
     * @throws \InvalidArgumentException for invalid request target.
     */
    private function filterRequestTarget(string $requestTarget): string
    {
        if ($requestTarget === '' || \preg_match('/[\x00-\x20\x7F]/', $requestTarget) === 1) {
            throw new \InvalidArgumentException('Invalid request target.');
        }

        return $requestTarget;
    }

    #[\Override]
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }
        $path = $this->uri->getPath();
        $query = $this->uri->getQuery();
        return ($path === '' ? '/' : $path) . ($query === '' ? '' : '?' . $query);
    }

    #[\Override]
    public function withRequestTarget(string $requestTarget): static
    {
        if ($requestTarget === $this->requestTarget) {
            return $this;
        }

        $newInstance = clone $this;
        $newInstance->requestTarget = $newInstance->filterRequestTarget($requestTarget);
        return $newInstance;
    }

    /** @throws \InvalidArgumentException for invalid HTTP method tokens. */
    private function filterMethod(string $method): string
    {
        if (\preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $method) !== 1) {
            throw new \InvalidArgumentException('Invalid HTTP method token.');
        }

        return $method;
    }

    #[\Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    #[\Override]
    public function withMethod(string $method): static
    {
        if ($this->method === $method) {
            return $this;
        }

        $newInstance = clone $this;
        $newInstance->method = $newInstance->filterMethod($method);
        return $newInstance;
    }

    #[\Override]
    public function getUri(): Uri
    {
        return $this->uri;
    }

    #[\Override]
    public function withUri(UriInterface $uri, $preserveHost = false): RequestInterface
    {
        $newInstance = clone $this;
        $newInstance->uri = $uri instanceof Uri ? $uri : new Uri($uri);
        $newInstance->queryParams = [];
        \parse_str($newInstance->uri->getQuery(), $newInstance->queryParams);

        if (!$preserveHost || $this->getHeaderLine('Host') === '') {
            $newInstance->setHostHeaderFromUri();
        }

        return $this->uri === $newInstance->uri && $this->headers === $newInstance->headers ? $this : $newInstance;
    }

    private function setHostHeaderFromUri(): void
    {
        $host = $this->uri->getHost();
        if ($host === '') {
            return;
        }

        $port = $this->uri->getPort();
        if ($port !== null) {
            $host .= ":$port";
        }

        if ($this->hasHeader('Host')) {
            $this->headers['host']['values'] = [$host];
        } else {
            $this->headers['host'] = ['name' => 'Host', 'values' => [$host]];
        }
    }

    /** @return array<string, string> */
    private static function getHttpHeaders(): array
    {
        $rawHeaders = [];
        if (\function_exists('getallheaders')) {
            foreach (\getallheaders() as $name => $value) {
                if (\is_string($name) && \is_string($value)) {
                    $rawHeaders[$name] = $value;
                }
            }
        } else {
            foreach ($_SERVER as $name => $value) {
                if (
                    \is_string($name)
                    && \str_starts_with($name, 'HTTP_')
                    && \is_string($value)
                ) {
                    $rawHeaders[\str_replace(
                        ' ',
                        '-',
                        \ucwords(\strtolower(\str_replace('_', ' ', \substr($name, 5))))
                    )] = $value;
                }
            }
        }

        return $rawHeaders;
    }
}
