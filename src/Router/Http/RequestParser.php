<?php

namespace dvictorjhg\braidphp\Router\Http;

final class RequestParser
{
    private const int READ_CHUNK_BYTES = 8192;

    /**
     * @param resource $resource
     */
    public static function fromResource(mixed $resource, ?HttpOptions $options = null): ?Request
    {
        $rawRequest = self::readRawRequest($resource, $options ?? new HttpOptions());
        return $rawRequest === null ? null : self::fromRawRequest($rawRequest);
    }

    /**
     * @param resource $resource
     * @return string|null
     */
    private static function readRawRequest(mixed $resource, HttpOptions $options): ?string
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('The request source must be a stream resource.');
        }

        $deadline = \microtime(true) + $options->readTimeout;
        $request = self::readHeaders($resource, $deadline, $options->maxHeaderBytes);
        if ($request === null) {
            return null;
        }

        $remaining = self::contentLength($request, $options->maxBodyBytes);
        while ($remaining > 0) {
            $chunk = self::readChunk($resource, \min(self::READ_CHUNK_BYTES, $remaining), $deadline);
            if ($chunk === false || $chunk === '') {
                throw new \InvalidArgumentException('Request body is shorter than Content-Length.');
            }
            $request .= $chunk;
            $remaining -= \strlen($chunk);
        }

        return $request;
    }

    /** @param resource $resource */
    private static function readHeaders(mixed $resource, float $deadline, int $maxHeaderBytes): ?string
    {
        $request = '';
        while (true) {
            $line = self::readChunk($resource, $maxHeaderBytes - \strlen($request) + 2, $deadline, true);
            if ($line === false || $line === '') {
                if ($request === '') {
                    return null;
                }
                throw new \InvalidArgumentException('The raw request is missing its header separator.');
            }
            $request .= $line;
            if (\strlen($request) > $maxHeaderBytes) {
                throw new \InvalidArgumentException('Request headers exceed the size limit.');
            }
            if ($line === "\r\n") {
                return $request;
            }
        }
    }

    private static function contentLength(string $request, int $maxBodyBytes): int
    {
        $contentLength = 0;
        $hasContentLength = false;
        foreach (\array_slice(\explode("\r\n", $request), 1, -2) as $header) {
            $parts = \explode(':', $header, 2);
            if (\count($parts) !== 2 || $parts[0] === '' || $parts[0] !== \trim($parts[0])) {
                throw new \InvalidArgumentException('The raw request contains an invalid header.');
            }
            [$name, $value] = $parts;
            if (\strcasecmp($name, 'Transfer-Encoding') === 0) {
                throw new \InvalidArgumentException('Transfer-Encoding is not supported.');
            }
            if (\strcasecmp($name, 'Content-Length') !== 0) {
                continue;
            }
            $value = \trim($value, " \t");
            if ($hasContentLength || !\ctype_digit($value)) {
                throw new \InvalidArgumentException('Invalid or duplicate Content-Length.');
            }
            $hasContentLength = true;
            $value = \ltrim($value, '0');
            $maximum = (string) $maxBodyBytes;
            if (
                \strlen($value) > \strlen($maximum)
                || (\strlen($value) === \strlen($maximum) && \strcmp($value, $maximum) > 0)
            ) {
                throw new \InvalidArgumentException('Request body exceeds the size limit.');
            }
            $contentLength = (int) $value;
        }

        return $contentLength;
    }

    /** @param resource $resource */
    private static function readChunk(mixed $resource, int $length, float $deadline, bool $line = false): string|false
    {
        if ($length < 1 || ($line && $length === 1)) {
            throw new \InvalidArgumentException('Invalid request read length.');
        }
        $remaining = $deadline - \microtime(true);
        if ($remaining <= 0) {
            throw new \InvalidArgumentException('Request read timed out.');
        }
        $metadata = \stream_get_meta_data($resource);
        $socket = \str_contains($metadata['stream_type'], 'socket');
        if ($socket) {
            $seconds = (int) $remaining;
            \stream_set_timeout($resource, $seconds, (int) (($remaining - $seconds) * 1_000_000));
        }

        $chunk = $line ? \fgets($resource, $length) : \fread($resource, $length);
        $metadata = \stream_get_meta_data($resource);
        if (($socket && $metadata['timed_out']) || \microtime(true) >= $deadline) {
            throw new \InvalidArgumentException('Request read timed out.');
        }
        if ($chunk === false && !\feof($resource)) {
            throw new \InvalidArgumentException('Failed to read request stream.');
        }

        return $chunk;
    }

    private static function fromRawRequest(string $rawRequest): Request
    {
        $parts = \explode("\r\n\r\n", $rawRequest, 2);
        if (\count($parts) !== 2) {
            throw new \InvalidArgumentException('The raw request is missing its header separator.');
        }

        [$headers, $body] = $parts;
        $headerLines = \explode("\r\n", $headers);
        $requestLine = \array_shift($headerLines);
        if ($requestLine === '') {
            throw new \InvalidArgumentException('The raw request has no request line.');
        }

        $requestLineParts = \explode(' ', $requestLine, 3);
        if (\count($requestLineParts) !== 3) {
            throw new \InvalidArgumentException('The raw request line is invalid.');
        }

        [$method, $uri, $protocol] = $requestLineParts;
        if (\preg_match('/^HTTP\/(\d\.\d)$/D', $protocol, $protocolParts) !== 1) {
            throw new \InvalidArgumentException('The raw request protocol is invalid.');
        }

        $headerArray = [];
        foreach ($headerLines as $header) {
            $headerParts = \explode(':', $header, 2);
            if (\count($headerParts) !== 2) {
                throw new \InvalidArgumentException('The raw request contains an invalid header.');
            }

            [$name, $value] = $headerParts;
            $headerArray[$name] = \ltrim($value);
        }

        $request = new Request($protocolParts[1], $method, $uri, $headerArray, $body);
        $parsedUri = $request->getUri();
        $query = $parsedUri->getQuery();
        $originForm = $parsedUri->getPath() . ($query === '' ? '' : '?' . $query);
        $absoluteForm = $parsedUri->getScheme() . '://' . $parsedUri->getAuthority() . $originForm;
        $validTarget = $method === 'CONNECT'
            ? $uri === $parsedUri->getAuthority()
            : $uri === $originForm || $uri === $absoluteForm || ($method === 'OPTIONS' && $uri === '*');
        if (!$validTarget) {
            throw new \InvalidArgumentException('Invalid request target for the HTTP method.');
        }
        return $request->withRequestTarget($uri);
    }
}
