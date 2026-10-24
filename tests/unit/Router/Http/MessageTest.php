<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\Message;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\Http\StreamFactory;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    public function testReadsHeadersAndStreamBody(): void
    {
        $body = StreamFactory::create('body');
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

    #[DataProvider('invalidProtocolVersions')]
    public function testRejectsInvalidProtocolVersions(string $version, bool $construct): void
    {
        $message = new Response();
        $this->expectException(\InvalidArgumentException::class);

        if ($construct) {
            self::assertSame($version, (new Response(protocolVersion: $version))->getProtocolVersion());
        } else {
            $message->withProtocolVersion($version);
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function invalidProtocolVersions(): iterable
    {
        $versions = [
            '',
            'HTTP/1.1',
            '1.1 extra',
            "1.1\r\nX-Injected: yes",
            "1.1\0",
            "1.1\n",
            '2 200 OK',
            '-1',
            '1.1.1',
        ];
        foreach ($versions as $version) {
            foreach ([true, false] as $construct) {
                yield ($construct ? 'construct: ' : 'replace: ') . \bin2hex($version) => [$version, $construct];
            }
        }
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

        $newBody = StreamFactory::create('new body');
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

    #[DataProvider('invalidHeaders')]
    public function testRejectsInvalidHeaders(string $operation, string $name, string $value): void
    {
        $message = new Response(headers: ['X-Trace' => 'safe']);
        $this->expectException(\InvalidArgumentException::class);

        match ($operation) {
            'construct' => new Response(headers: [$name => $value]),
            'replace' => $message->withHeader($name, $value),
            'append existing' => $message->withAddedHeader('X-Trace', ['safe', $value]),
            default => $message->withAddedHeader($name, $value),
        };
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidHeaders(): iterable
    {
        $headers = [
            'empty name' => ['', 'safe'],
            'space in name' => ['X Trace', 'safe'],
            'colon in name' => ['X:Trace', 'safe'],
            'injected name' => ["X-Trace\r\nX-Injected", 'safe'],
            'injected value' => ['X-Trace', "safe\r\nX-Injected: yes"],
            'bare carriage return' => ['X-Trace', "safe\rvalue"],
            'bare newline' => ['X-Trace', "safe\nvalue"],
            'null byte' => ['X-Trace', "safe\0value"],
            'control byte' => ['X-Trace', "safe\x1Fvalue"],
            'delete byte' => ['X-Trace', "safe\x7Fvalue"],
        ];
        foreach (['construct', 'replace', 'append', 'append existing'] as $operation) {
            foreach ($headers as $label => [$name, $value]) {
                if ($operation === 'append existing' && $name !== 'X-Trace') {
                    continue;
                }
                yield "$operation: $label" => [$operation, $name, $value];
            }
        }
    }

    public function testAcceptsValidHeaderTokensAndValues(): void
    {
        $name = "X-!#$%&'*+.^_`|~";
        $value = "\t visible \x80\xFF";
        $message = new Response(headers: [$name => $value]);

        self::assertSame([$value], $message->getHeader($name));
        self::assertSame([$value, ''], $message->withAddedHeader($name, '')->getHeader($name));
    }
}
