<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\RequestParser;

#[CoversClass(RequestParser::class)]
final class RequestParserTest extends TestCase
{
    public function testRejectsNonResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The request source must be a stream resource.');

        $method = new \ReflectionMethod(RequestParser::class, 'fromResource');
        $method->invoke(null, null);
    }

    public function testReturnsNullForEmptyResource(): void
    {
        $resource = \fopen('php://temp', 'r');
        self::assertIsResource($resource);

        try {
            self::assertNull(RequestParser::fromResource($resource));
        } finally {
            \fclose($resource);
        }
    }

    public function testParsesRequestWithoutBody(): void
    {
        $resource = $this->resourceFrom(
            "GET /hello?name=Victor HTTP/1.1\r\n"
            . "Host: example.test\r\n"
            . "\r\n"
        );

        try {
            $request = RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }

        self::assertNotNull($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/hello?name=Victor', $request->getRequestTarget());
        self::assertSame(['example.test'], $request->getHeader('Host'));
        self::assertSame('', (string) $request->getBody());
    }

    public function testParsesRequestBodyUsingContentLength(): void
    {
        $resource = $this->resourceFrom(
            "POST /hello HTTP/1.1\r\n"
            . "Content-Length: 7\r\n"
            . "\r\n"
            . "payload"
        );

        try {
            $request = RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }

        self::assertNotNull($request);
        self::assertSame('payload', (string) $request->getBody());
    }

    public function testParsesAvailableBodyWhenContentLengthIsTooLarge(): void
    {
        $resource = $this->resourceFrom(
            "POST /hello HTTP/1.1\r\n"
            . "Content-Length: 10\r\n"
            . "\r\n"
            . "short"
        );

        try {
            $request = RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }

        self::assertNotNull($request);
        self::assertSame('short', (string) $request->getBody());
    }

    public function testRejectsRawRequestWithoutHeaderSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The raw request is missing its header separator.');

        $resource = $this->resourceFrom("GET / HTTP/1.1\r\nHost: example.test\r\n");
        try {
            RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }
    }

    public function testRejectsRawRequestWithoutRequestLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The raw request has no request line.');

        $method = new \ReflectionMethod(RequestParser::class, 'fromRawRequest');
        $method->invoke(null, "\r\n\r\n");
    }

    public function testRejectsInvalidRequestLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The raw request line is invalid.');

        $resource = $this->resourceFrom("GET /hello\r\n\r\n");
        try {
            RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }
    }

    public function testRejectsInvalidHeader(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The raw request contains an invalid header.');

        $resource = $this->resourceFrom("GET / HTTP/1.1\r\nInvalid-Header\r\n\r\n");
        try {
            RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }
    }

    /** @return resource */
    private function resourceFrom(string $contents)
    {
        $resource = \fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        \fwrite($resource, $contents);
        \rewind($resource);

        return $resource;
    }
}
