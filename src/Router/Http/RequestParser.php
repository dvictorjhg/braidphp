<?php

namespace dvictorjhg\braidphp\Router\Http;

final class RequestParser
{
    /**
     * @param resource $resource
     */
    public static function fromResource(mixed $resource): ?Request
    {
        $rawRequest = self::readRawRequest($resource);
        return $rawRequest === null ? null : self::fromRawRequest($rawRequest);
    }

    /**
     * @param resource $resource
     * @return string|null
     */
    private static function readRawRequest(mixed $resource): ?string
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('The request source must be a stream resource.');
        }

        $request = '';

        while (($line = \fgets($resource)) !== false) {
            $request .= $line;
            if (\trim($line) === '') {
                break;
            }
        }

        if ($request === '') {
            return null;
        }

        $contentLength = 0;
        if (\preg_match('/Content-Length: (\d+)/i', $request, $matches)) {
            $contentLength = (int) $matches[1];
        }

        if ($contentLength > 0) {
            $body = '';
            $remaining = $contentLength;

            while ($remaining > 0 && !\feof($resource)) {
                $chunk = \fread($resource, \min(8192, $remaining));
                if ($chunk === false) {
                    break;
                }
                $body .= $chunk;
                $remaining -= \strlen($chunk);
            }

            $request .= $body;
        }

        return $request;
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

        $headerArray = [];
        foreach ($headerLines as $header) {
            if ($header === '') {
                continue;
            }

            $headerParts = \explode(':', $header, 2);
            if (\count($headerParts) !== 2) {
                throw new \InvalidArgumentException('The raw request contains an invalid header.');
            }

            [$name, $value] = $headerParts;
            $headerArray[$name] = \ltrim($value);
        }

        return new Request($protocol, $method, $uri, $headerArray, $body);
    }
}
