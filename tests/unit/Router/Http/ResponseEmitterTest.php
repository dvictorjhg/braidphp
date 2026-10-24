<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use dvictorjhg\braidphp\Router\Http\HttpOptions;
use dvictorjhg\braidphp\Router\Http\HttpSyntax;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\Http\ResponseEmitter;
use dvictorjhg\braidphp\Router\Http\StreamException;
use dvictorjhg\braidphp\tests\Mock\ShortWriteStream;

#[CoversClass(ResponseEmitter::class)]
#[CoversClass(HttpSyntax::class)]
final class ResponseEmitterTest extends TestCase
{
    public function testSerializesDefaultsAndBody(): void
    {
        self::assertSame(
            "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: 5\r\n\r\nhello",
            $this->emit(new Response(body: 'hello'))
        );
    }

    public function testPreservesExplicitHeadersAndProtocol(): void
    {
        self::assertSame(
            "HTTP/1.0 202 Custom\r\nContent-Type: application/json\r\nContent-Length: 99\r\n"
                . "X-Test: first\r\nX-Test: second\r\n\r\nhello",
            $this->emit(new Response(
                202,
                ['Content-Type' => 'application/json', 'Content-Length' => '99', 'X-Test' => ['first', 'second']],
                'hello',
                '1.0',
                'Custom'
            ))
        );
    }

    public function testRejectsExpiredWriteDeadline(): void
    {
        $client = \fopen('php://temp', 'w+');
        self::assertIsResource($client);
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Response write timed out.');
        try {
            ResponseEmitter::write($client, new Response(), new HttpOptions(writeTimeout: PHP_FLOAT_MIN));
        } finally {
            \fclose($client);
        }
    }

    /** @param array<string, list<mixed>> $headers */
    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidForeignMetadataBeforeWriting(
        string $version,
        int $status,
        string $reason,
        array $headers
    ): void {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getProtocolVersion')->willReturn($version);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getReasonPhrase')->willReturn($reason);
        $response->method('getHeaders')->willReturn($headers);
        $client = \fopen('php://temp', 'w+');
        self::assertIsResource($client);
        $this->expectException(\InvalidArgumentException::class);
        try {
            ResponseEmitter::write($client, $response);
        } finally {
            self::assertSame(0, \ftell($client));
            \fclose($client);
        }
    }

    /** @return iterable<string, array{string, int, string, array<string, list<mixed>>}> */
    public static function invalidMetadata(): iterable
    {
        yield 'protocol' => ["1.1\r\nInjected: yes", 200, 'OK', []];
        yield 'status' => ['1.1', 99, 'OK', []];
        yield 'reason' => ['1.1', 200, "OK\r\nInjected: yes", []];
        yield 'header name' => ['1.1', 200, 'OK', ["X-Test\r\nInjected" => ['yes']]];
        yield 'header value' => ['1.1', 200, 'OK', ['X-Test' => ["yes\r\nInjected: yes"]]];
        yield 'header value type' => ['1.1', 200, 'OK', ['X-Test' => [1]]];
    }

    public function testCompletesShortWrites(): void
    {
        $response = new Response(body: \str_repeat('body', 4096));
        $client = $this->shortWriteClient();
        try {
            ResponseEmitter::write($client, $response);
            self::assertSame($this->emit($response), ShortWriteStream::$written);
        } finally {
            \fclose($client);
            \stream_wrapper_unregister('shortwrite');
        }
    }

    public function testRejectsStalledWrites(): void
    {
        $client = $this->shortWriteClient();
        ShortWriteStream::$stall = true;
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to write response to client.');
        try {
            ResponseEmitter::write($client, new Response());
        } finally {
            \fclose($client);
            \stream_wrapper_unregister('shortwrite');
        }
    }

    public function testSocketWriteDoesNotWaitForeverForSlowReader(): void
    {
        $sockets = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);
        $this->expectException(StreamException::class);
        try {
            ResponseEmitter::write(
                $sockets[0],
                new Response(body: \str_repeat('a', 8_388_608)),
                new HttpOptions(writeTimeout: 0.02)
            );
        } finally {
            \fclose($sockets[0]);
            \fclose($sockets[1]);
        }
    }

    private function emit(Response $response): string
    {
        $client = \fopen('php://temp', 'w+');
        self::assertIsResource($client);
        try {
            ResponseEmitter::write($client, $response);
            \rewind($client);
            $payload = \stream_get_contents($client);
            self::assertIsString($payload);
            return $payload;
        } finally {
            \fclose($client);
        }
    }

    /** @return resource */
    private function shortWriteClient(): mixed
    {
        ShortWriteStream::$written = '';
        ShortWriteStream::$stall = false;
        ShortWriteStream::$pause = false;
        self::assertTrue(\stream_wrapper_register('shortwrite', ShortWriteStream::class));
        $client = \fopen('shortwrite://response', 'w');
        self::assertIsResource($client);
        return $client;
    }
}
