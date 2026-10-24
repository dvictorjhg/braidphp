<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\ServerRequestState;

#[CoversClass(Request::class)]
#[CoversClass(ServerRequestState::class)]
final class ServerRequestTest extends TestCase
{
    public function testServerRequestStateIsImmutable(): void
    {
        $request = new Request(uri: '/?original=yes', body: '');
        $file = $this->createStub(UploadedFileInterface::class);
        $changed = $request->withCookieParams(['session' => 'value'])
            ->withQueryParams(['page' => 2])
            ->withUploadedFiles(['nested' => ['file' => $file]])
            ->withParsedBody(['name' => 'Victor'])
            ->withAttribute('user', 42)
            ->withAttribute('nullable', null);

        self::assertInstanceOf(ServerRequestInterface::class, $request);
        self::assertSame($_SERVER, $request->getServerParams());
        self::assertSame($_COOKIE, $request->getCookieParams());
        self::assertSame(['original' => 'yes'], $request->getQueryParams());
        self::assertSame([], $request->getUploadedFiles());
        self::assertNull($request->getParsedBody());
        self::assertSame([], $request->getAttributes());
        self::assertSame(['session' => 'value'], $changed->getCookieParams());
        self::assertSame(['page' => 2], $changed->getQueryParams());
        self::assertSame('original=yes', $changed->getUri()->getQuery());
        self::assertSame(['nested' => ['file' => $file]], $changed->getUploadedFiles());
        self::assertSame(['name' => 'Victor'], $changed->getParsedBody());
        self::assertSame(42, $changed->getAttribute('user'));
        self::assertNull($changed->getAttribute('nullable', 'fallback'));
        self::assertSame('fallback', $changed->getAttribute('missing', 'fallback'));
        self::assertSame(['nullable' => null], $changed->withoutAttribute('user')->getAttributes());
        self::assertSame(42, $changed->getAttribute('user'));
        $body = new \stdClass();
        self::assertSame($body, $request->withParsedBody($body)->getParsedBody());
        self::assertNull($changed->withParsedBody(null)->getParsedBody());
    }

    public function testRejectsInvalidParsedBody(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $method = new \ReflectionMethod(Request::class, 'withParsedBody');
        $method->invoke(new Request(body: ''), 'invalid');
    }

    public function testFormPostsExposePhpParsedBody(): void
    {
        $_POST = ['name' => 'Ada'];
        foreach (['application/x-www-form-urlencoded; charset=UTF-8', 'multipart/form-data; boundary=test'] as $type) {
            $request = new Request(method: 'POST', headers: ['Content-Type' => $type], body: '');
            self::assertSame($_POST, $request->getParsedBody());
        }
        self::assertNull((new Request(method: 'POST', headers: ['Content-Type' => 'application/json'], body: ''))
            ->getParsedBody());
    }

    public function testRejectsInvalidNestedUploadedFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Request(body: ''))->withUploadedFiles(['nested' => ['file' => new \stdClass()]]);
    }

    public function testParsedBodyInitializationUsesTheConstructedMethod(): void
    {
        $_POST = ['name' => 'Ada'];
        $request = new class (
            method: 'POST',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: ''
        ) extends Request {
            #[\Override]
            public function getMethod(): string
            {
                return 'GET';
            }
        };

        self::assertSame('GET', $request->getMethod());
        self::assertSame($_POST, $request->getParsedBody());
    }
}
