<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\UriNormalizer;

#[CoversClass(UriNormalizer::class)]
final class UriNormalizerTest extends TestCase
{
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

    public function testPreservesEncodedDelimitersAndEncodesInvalidEscapes(): void
    {
        self::assertSame('/a%2Fb/%252F/a+b', UriNormalizer::normalizePath('/a%2Fb/%252F/a+b'));
        self::assertSame('q=a%26b%3Dc&plus=%2B', UriNormalizer::normalizeQuery('q=a%26b%3Dc&plus=%2B'));
        self::assertSame('/space%20here/%25zz', UriNormalizer::normalizePath('/space here/%zz'));
        self::assertSame('q=%25zz%20here', UriNormalizer::normalizeQuery('q=%zz here'));
    }
}
