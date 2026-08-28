<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Attributes;

use dvictorjhg\braidphp\Router\Attributes\Connect;
use dvictorjhg\braidphp\Router\Attributes\Delete;
use dvictorjhg\braidphp\Router\Attributes\Get;
use dvictorjhg\braidphp\Router\Attributes\Head;
use dvictorjhg\braidphp\Router\Attributes\Options;
use dvictorjhg\braidphp\Router\Attributes\Patch;
use dvictorjhg\braidphp\Router\Attributes\Post;
use dvictorjhg\braidphp\Router\Attributes\Put;
use dvictorjhg\braidphp\Router\Attributes\Route as RouteAttribute;
use dvictorjhg\braidphp\Router\Attributes\Trace;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class HttpMethodAttributesTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<RouteAttribute>, HttpMethod}>
     */
    public static function httpMethodAttributes(): iterable
    {
        yield 'GET' => [Get::class, HttpMethod::GET];
        yield 'HEAD' => [Head::class, HttpMethod::HEAD];
        yield 'POST' => [Post::class, HttpMethod::POST];
        yield 'PUT' => [Put::class, HttpMethod::PUT];
        yield 'DELETE' => [Delete::class, HttpMethod::DELETE];
        yield 'CONNECT' => [Connect::class, HttpMethod::CONNECT];
        yield 'OPTIONS' => [Options::class, HttpMethod::OPTIONS];
        yield 'TRACE' => [Trace::class, HttpMethod::TRACE];
        yield 'PATCH' => [Patch::class, HttpMethod::PATCH];
    }

    /**
     * @param class-string<RouteAttribute> $attributeClass
     */
    #[DataProvider('httpMethodAttributes')]
    public function testAttributeUsesExpectedHttpMethod(
        string $attributeClass,
        HttpMethod $httpMethod
    ): void {
        $attribute = (new ReflectionClass($attributeClass))->newInstance('/resource', 'full');

        self::assertInstanceOf(RouteAttribute::class, $attribute);
        self::assertSame($httpMethod, $attribute->httpMethod);
        self::assertSame('/resource', $attribute->path);
        self::assertSame('full', $attribute->pathMatch);
    }
}
