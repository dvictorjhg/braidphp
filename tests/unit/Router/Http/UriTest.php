<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\Uri;

#[CoversClass(Uri::class)]
final class UriTest extends TestCase
{
    public function testChangingPathRefreshesPathParts(): void
    {
        $uri = new Uri('/old/path');
        $changedUri = $uri->withPath('/new/%E2%9C%93');

        self::assertSame(['old', 'path'], $uri->getPathParts());
        self::assertSame(['new', '%E2%9C%93'], $changedUri->getPathParts());
        self::assertSame('/new/%E2%9C%93', (string) $changedUri);
    }

    public function testReadsUriComponentsAndAuthority(): void
    {
        $uri = new Uri('HTTPS://user:password@EXAMPLE.COM:8443/path?query=value#fragment');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:password', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('query=value', $uri->getQuery());
        self::assertSame('fragment', $uri->getFragment());
        self::assertSame('user:password@example.com:8443', $uri->getAuthority());
        self::assertSame(
            'https://user:password@example.com:8443/path?query=value#fragment',
            (string) $uri
        );
    }

    public function testCreatesUriFromAnotherUri(): void
    {
        $source = new Uri('https://example.com/path');
        $copy = new Uri($source);

        self::assertSame((string) $source, (string) $copy);
        self::assertSame($source->getPathParts(), $copy->getPathParts());
    }

    public function testAuthorityIsEmptyWithoutHost(): void
    {
        self::assertSame('', (new Uri('/path'))->getAuthority());
    }

    public function testRejectsUnparseableUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The URI 'http://:bad' could not be parsed.");

        new Uri('http://:bad');
    }

    public function testWithMethodsAreImmutableAndReturnSameInstanceForSameValue(): void
    {
        $uri = new Uri('http://example.com/old?one=1#old');

        self::assertSame($uri, $uri->withScheme('HTTP'));
        self::assertSame('https', $uri->withScheme('HTTPS')->getScheme());
        self::assertSame($uri, $uri->withUserInfo(''));
        self::assertSame('user:password', $uri->withUserInfo('user', 'password')->getUserInfo());
        self::assertSame($uri, $uri->withHost('EXAMPLE.COM'));
        self::assertSame('new.example.com', $uri->withHost('NEW.EXAMPLE.COM')->getHost());
        self::assertSame($uri, $uri->withPort(null));
        self::assertSame(8443, $uri->withPort(8443)->getPort());
        self::assertSame($uri, $uri->withPath('/old'));
        self::assertSame('/new', $uri->withPath('/new')->getPath());
        self::assertSame($uri, $uri->withQuery('one=1'));
        self::assertSame('two=2', $uri->withQuery('two=2')->getQuery());
        self::assertSame($uri, $uri->withFragment('old'));
        self::assertSame('new', $uri->withFragment('new')->getFragment());

        self::assertSame('/old', $uri->getPath());
        self::assertSame('one=1', $uri->getQuery());
        self::assertSame('old', $uri->getFragment());
    }

    public function testFormatsRelativeAndMultipleLeadingSlashPaths(): void
    {
        $relativePath = (new Uri('http://example.com'))->withPath('relative');
        $multipleLeadingSlashes = (new Uri('/path'))->withPath('//path');

        self::assertSame('http://example.com/relative', (string) $relativePath);
        self::assertSame('/path', (string) $multipleLeadingSlashes);
    }
}
