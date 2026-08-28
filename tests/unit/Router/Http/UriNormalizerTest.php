<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\UriNormalizer;

#[CoversClass(UriNormalizer::class)]
final class UriNormalizerTest extends TestCase
{
    public function testNormalizesScheme(): void
    {
        self::assertSame('https', UriNormalizer::normalizeScheme('HTTPS'));
    }

    public function testNormalizesUserInfo(): void
    {
        self::assertSame('', UriNormalizer::normalizeUserInfo());
        self::assertSame('user', UriNormalizer::normalizeUserInfo('user'));
        self::assertSame('user:password', UriNormalizer::normalizeUserInfo('user', 'password'));
    }

    public function testNormalizesHost(): void
    {
        self::assertSame('example.com', UriNormalizer::normalizeHost('EXAMPLE.COM'));
        self::assertSame('[2001:db8::1]', UriNormalizer::normalizeHost('2001:DB8::1'));
    }

    public function testNormalizesPathAndQuery(): void
    {
        self::assertSame('/hello%20world', UriNormalizer::normalizePath('/hello%20world'));
        self::assertSame('q=hello%20world', UriNormalizer::normalizeQuery('q=hello%20world'));
    }

    public function testPreservesFragment(): void
    {
        self::assertSame('section%201', UriNormalizer::normalizeFragment('section%201'));
    }

    public function testSplitsPath(): void
    {
        self::assertSame([], UriNormalizer::splitPath(''));
        self::assertSame(['one', 'two'], UriNormalizer::splitPath('/one/two'));
    }
}
