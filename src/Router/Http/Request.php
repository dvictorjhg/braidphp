<?php

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request extends Message implements RequestInterface
{
    private string $method;
    private Uri $uri;
    private string $requestTarget;
    /** @var array<string, string> */
    private array $routeParams = [];
    /** @var array<string|int, mixed> */
    private array $queryParams = [];

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
     *
     * @throws \InvalidArgumentException If any of the inputs are invalid or cannot be processed.
     * @throws RequestException If the input stream (`php://input`) cannot be opened.
     */
    public function __construct(
        ?string $protocolVersion = null,
        HttpMethod|string|null $method = null,
        UriInterface|string|null $uri = null,
        array $headers = [],
        mixed $body = null
    ) {
        $serverProtocol = $_SERVER['SERVER_PROTOCOL'] ?? null;
        $protocolVersion ??= \is_string($serverProtocol) && \str_contains($serverProtocol, '/')
            ? \explode('/', $serverProtocol, 2)[1]
            : '1.1';

        $serverMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $method ??= \is_string($serverMethod) ? $serverMethod : 'GET';

        $serverUri = $_SERVER['REQUEST_URI'] ?? null;
        $uri ??= \is_string($serverUri) ? $serverUri : '/';

        $headers = $headers ?: self::getHttpHeaders();

        if ($body === null) {
            $resource = @\fopen('php://input', 'r');
            if ($resource === false) {
                throw new RequestException('Failed to open input stream from php://input');
            }
            $body = new Stream($resource);
        }

        $this->method = $this->filterMethod(($method instanceof HttpMethod) ? $method->name : $method);

        $this->uri = $uri instanceof Uri ? $uri : new Uri($uri);

        $this->requestTarget = $this->filterRequestTarget((string) $this->uri);

        parent::__construct($headers, $body, $protocolVersion);

        if (!$this->hasHeader('Host')) {
            $this->setHostHeaderFromUri();
        }

        \parse_str($this->uri->getQuery(), $this->queryParams);
    }

    /**
     * @param resource $resource
     * @return Request|null
     */
    public static function fromResource(mixed $resource): ?Request
    {
        return RequestParser::fromResource($resource);
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
        $originForm = $this->uri->getPath() . ($this->uri->getQuery() ? '?' . $this->uri->getQuery() : '');
        $absoluteForm = $this->uri->getScheme() . '://' .
            $this->uri->getAuthority() .
            $this->uri->getPath() .
            ($this->uri->getQuery() ? '?' . $this->uri->getQuery() : '');
        $authorityForm = $this->uri->getAuthority();
        $asteriskForm = '*';

        $isConnectOrOptions = \in_array($this->method, ['CONNECT', 'OPTIONS']);
        $isOriginOrAbsoluteForm = $requestTarget === $originForm || $requestTarget === $absoluteForm;
        $isConnectAndAuthorityForm = $this->method === 'CONNECT' && $requestTarget === $authorityForm;
        $isOptionsAndAsteriskForm = $this->method === 'OPTIONS' && $requestTarget === $asteriskForm;

        if (
            (!$isConnectOrOptions && $isOriginOrAbsoluteForm)
            || $isConnectAndAuthorityForm
            || $isOptionsAndAsteriskForm
        ) {
            return $requestTarget;
        }

        throw new \InvalidArgumentException("Invalid target '$requestTarget' for '$this->method' method.");
    }

    #[\Override]
    public function getRequestTarget(): string
    {
        return $this->requestTarget;
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

    /** @throws \InvalidArgumentException for unsupported methods. */
    private function filterMethod(string $method): string
    {
        if (!\in_array($method, \array_column(HttpMethod::cases(), 'name'), true)) {
            throw new \InvalidArgumentException("Unsupported method given ($method).");
        }

        return $method;
    }

    #[\Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    #[\Override]
    public function withMethod(string $method): RequestInterface
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
        if ($this->uri === $uri) {
            return $this;
        }

        $newInstance = clone $this;
        $newInstance->uri = $uri instanceof Uri ? $uri : new Uri($uri);
        $newInstance->requestTarget = $newInstance->filterRequestTarget((string) $newInstance->uri);
        $newInstance->queryParams = [];
        \parse_str($newInstance->uri->getQuery(), $newInstance->queryParams);

        if (!$preserveHost) {
            $newInstance->setHostHeaderFromUri();
        }

        return $newInstance;
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

    /** @return array<string|int, mixed> */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getRouteParam(string $name): ?string
    {
        return $this->routeParams[$name] ?? null;
    }

    /** @return array<string, string> */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * @param array<string, string> $params
     */
    public function withRouteParams(array $params): self
    {
        if ($params === $this->routeParams) {
            return $this;
        }

        $newInstance = clone $this;
        $newInstance->routeParams = $params;
        return $newInstance;
    }
}
