<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\HttpOptions;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\RequestParser;

#[CoversClass(RequestParser::class)]
#[CoversClass(HttpOptions::class)]
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
        self::assertSame('1.1', $request->getProtocolVersion());
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

    public function testParsesOptionsPreflightWithHttp10Protocol(): void
    {
        $resource = $this->resourceFrom(
            "OPTIONS /resource HTTP/1.0\r\nHost: example.test\r\nOrigin: https://example.test\r\n\r\n"
        );
        try {
            $request = Request::fromResource($resource);
            self::assertNotNull($request);
            self::assertSame('OPTIONS', $request->getMethod());
            self::assertSame('/resource', $request->getRequestTarget());
            self::assertSame('1.0', $request->getProtocolVersion());
        } finally {
            \fclose($resource);
        }
    }

    #[DataProvider('invalidProtocols')]
    public function testRejectsInvalidProtocol(string $protocol): void
    {
        $resource = $this->resourceFrom("GET / $protocol\r\n\r\n");
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The raw request protocol is invalid.');
        try {
            RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }
    }

    /** @return array<string, array{string}> */
    public static function invalidProtocols(): array
    {
        return [
            'missing prefix' => ['1.1'],
            'wrong protocol' => ['FTP/1.1'],
            'missing minor version' => ['HTTP/1'],
            'invalid version' => ['HTTP/one.two'],
            'extra token' => ['HTTP/1.1 extra'],
        ];
    }

    public function testRejectsTruncatedBody(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request body is shorter than Content-Length.');
        $resource = $this->resourceFrom(
            "POST /hello HTTP/1.1\r\n"
            . "Content-Length: 10\r\n"
            . "\r\n"
            . "short"
        );

        try {
            RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }
    }

    #[DataProvider('validLengths')]
    public function testParsesExactContentLengthHeader(string $header, string $expectedBody): void
    {
        $resource = $this->resourceFrom("POST / HTTP/1.1\r\n$header\r\n\r\ndata");
        try {
            $request = RequestParser::fromResource($resource);
            self::assertNotNull($request);
            self::assertSame($expectedBody, (string) $request->getBody());
        } finally {
            \fclose($resource);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function validLengths(): array
    {
        return [
            'no whitespace' => ['Content-Length:4', 'data'],
            'one byte' => ['Content-Length:1', 'd'],
            'case and tabs' => ["cOnTeNt-LeNgTh:\t4\t", 'data'],
            'leading zeros' => ['Content-Length: 0004', 'data'],
            'unrelated header' => ['X-Content-Length: 4', ''],
        ];
    }

    #[DataProvider('invalidFraming')]
    public function testRejectsInvalidFraming(string $header): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $resource = $this->resourceFrom("POST / HTTP/1.1\r\n$header\r\n\r\n");
        try {
            RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }
    }

    /** @return array<string, array{string}> */
    public static function invalidFraming(): array
    {
        return [
            'negative' => ['Content-Length: -1'],
            'non-numeric' => ['Content-Length: nope'],
            'empty' => ['Content-Length:'],
            'overflow' => ['Content-Length: 999999999999999999999999999999'],
            'body limit' => ['Content-Length: 8388609'],
            'duplicate' => ["Content-Length: 4\r\ncontent-length: 5"],
            'comma list' => ['Content-Length: 4, 4'],
            'whitespace in name' => ['Content-Length : 4'],
            'chunked' => ['Transfer-Encoding: chunked'],
            'ambiguous' => ["Transfer-Encoding: chunked\r\nContent-Length: 4"],
            'headers limit' => ['X-Large: ' . \str_repeat('a', 65_536)],
        ];
    }

    #[DataProvider('readModes')]
    public function testSocketReadsRespectDeadline(bool $line): void
    {
        $sockets = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);
        $method = new \ReflectionMethod(RequestParser::class, 'readChunk');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request read timed out.');
        try {
            $method->invoke(null, $sockets[0], 1024, \microtime(true) + 0.02, $line);
        } finally {
            \fclose($sockets[0]);
            \fclose($sockets[1]);
        }
    }

    /** @return array<string, array{bool}> */
    public static function readModes(): array
    {
        return ['header' => [true], 'body' => [false]];
    }

    public function testRejectsExpiredDeadlineEvenWhenDataIsAvailable(): void
    {
        $resource = $this->resourceFrom('data');
        $method = new \ReflectionMethod(RequestParser::class, 'readChunk');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request read timed out.');
        try {
            $method->invoke(null, $resource, 4, \microtime(true) - 1);
        } finally {
            \fclose($resource);
        }
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

    #[DataProvider('customLimits')]
    public function testUsesCustomLimits(string $raw, HttpOptions $options, ?string $error): void
    {
        $resource = $this->resourceFrom($raw);
        if ($error !== null) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage($error);
        }
        try {
            $request = Request::fromResource($resource, $options);
            self::assertNotNull($request);
            self::assertSame('data', (string) $request->getBody());
        } finally {
            \fclose($resource);
        }
    }

    /** @return array<string, array{string, HttpOptions, ?string}> */
    public static function customLimits(): array
    {
        $raw = "POST / HTTP/1.1\r\nContent-Length:4\r\n\r\ndata";
        return [
            'exact boundaries' => [$raw, new HttpOptions(maxHeaderBytes: \strlen($raw) - 4, maxBodyBytes: 4), null],
            'small headers' => [$raw, new HttpOptions(maxHeaderBytes: 20), 'headers exceed'],
            'small body' => [$raw, new HttpOptions(maxBodyBytes: 3), 'body exceeds'],
            'body disabled' => [$raw, new HttpOptions(maxBodyBytes: 0), 'body exceeds'],
            'raised body limit' => [
                "POST / HTTP/1.1\r\nContent-Length:10000000\r\n\r\n",
                new HttpOptions(maxBodyBytes: 10_000_000),
                'shorter than Content-Length',
            ],
            'integer overflow' => [
                "POST / HTTP/1.1\r\nContent-Length:9999999999999999999\r\n\r\n",
                new HttpOptions(maxBodyBytes: PHP_INT_MAX),
                'body exceeds',
            ],
        ];
    }

    public function testUsesCustomReadTimeout(): void
    {
        $sockets = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request read timed out.');
        try {
            RequestParser::fromResource($sockets[0], new HttpOptions(readTimeout: 0.02));
        } finally {
            \fclose($sockets[0]);
            \fclose($sockets[1]);
        }
    }

    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidOptions(string $option, int|float $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($option);
        (new \ReflectionClass(HttpOptions::class))->newInstanceArgs([$option => $value]);
    }

    /** @return array<string, array{string, int|float}> */
    public static function invalidOptions(): array
    {
        return [
            'headers' => ['maxHeaderBytes', 0],
            'headers overflow' => ['maxHeaderBytes', PHP_INT_MAX],
            'body' => ['maxBodyBytes', -1],
            'read zero' => ['readTimeout', 0.0],
            'read infinite' => ['readTimeout', INF],
            'write negative' => ['writeTimeout', -1.0],
            'write NaN' => ['writeTimeout', NAN],
            'backlog' => ['backlog', 0],
        ];
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
