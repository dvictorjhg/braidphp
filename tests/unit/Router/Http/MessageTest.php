<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\Message;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\Http\Stream;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    public function testReadsHeadersAndStreamBody(): void
    {
        $body = Stream::of('body');
        $message = new Response(
            headers: ['X-Trace' => ['one', 'two']],
            body: $body,
            protocolVersion: '2'
        );

        self::assertSame('2', $message->getProtocolVersion());
        self::assertSame(['X-Trace' => ['one', 'two']], $message->getHeaders());
        self::assertTrue($message->hasHeader('x-trace'));
        self::assertSame(['one', 'two'], $message->getHeader('X-Trace'));
        self::assertSame('one,two', $message->getHeaderLine('X-Trace'));
        self::assertSame([], $message->getHeader('Missing'));
        self::assertSame('', $message->getHeaderLine('Missing'));
        self::assertSame($body, $message->getBody());

        $body->close();
    }

    public function testCreatesStreamsForNullAndScalarBodies(): void
    {
        self::assertSame('', (string) (new Response())->getBody());
        self::assertSame('body', (string) (new Response(body: 'body'))->getBody());
    }

    public function testMessageMutatorsAreImmutable(): void
    {
        $message = new Response(headers: ['X-Trace' => 'one'], body: 'body');

        self::assertSame($message, $message->withProtocolVersion('1.1'));
        $versioned = $message->withProtocolVersion('2');
        self::assertSame('1.1', $message->getProtocolVersion());
        self::assertSame('2', $versioned->getProtocolVersion());

        $withHeader = $message->withHeader('X-New', 'new');
        self::assertFalse($message->hasHeader('X-New'));
        self::assertSame(['new'], $withHeader->getHeader('X-New'));

        $withAddedHeader = $message->withAddedHeader('X-Trace', ['two', 'three']);
        self::assertSame(['one'], $message->getHeader('X-Trace'));
        self::assertSame(['one', 'two', 'three'], $withAddedHeader->getHeader('X-Trace'));

        $withNewHeader = $message->withAddedHeader('X-New', 'new');
        self::assertSame(['new'], $withNewHeader->getHeader('X-New'));

        $withoutHeader = $withAddedHeader->withoutHeader('X-Trace');
        self::assertTrue($withAddedHeader->hasHeader('X-Trace'));
        self::assertFalse($withoutHeader->hasHeader('X-Trace'));
        self::assertSame($message, $message->withoutHeader('Missing'));

        $newBody = Stream::of('new body');
        $withBody = $message->withBody($newBody);
        self::assertNotSame($message->getBody(), $withBody->getBody());
        self::assertSame($newBody, $withBody->getBody());
        $newBody->close();
    }

    public function testRejectsNonStringHeaderValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header values must be strings.');

        $class = new \ReflectionClass(Response::class);
        $class->newInstanceArgs([
            200,
            ['X-Trace' => ['valid', 1]]
        ]);
    }
}
