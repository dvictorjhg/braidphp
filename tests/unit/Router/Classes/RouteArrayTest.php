<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Classes\RouteArray;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\tests\Mock\TestController;

#[CoversClass(RouteArray::class)]
final class RouteArrayTest extends TestCase
{
    public function testSupportsArrayAccessCountingAndIteration(): void
    {
        $firstRoute = $this->route('/first');
        $secondRoute = $this->route('/second');
        $routes = new RouteArray($firstRoute);

        self::assertTrue(isset($routes[0]));
        self::assertFalse(isset($routes[1]));
        self::assertSame($firstRoute, $routes[0]);

        $routes[] = $secondRoute;
        self::assertCount(2, $routes);

        $routes[0] = $secondRoute;
        self::assertSame($secondRoute, $routes[0]);

        unset($routes[1]);
        self::assertFalse(isset($routes[1]));
        self::assertSame([$secondRoute], iterator_to_array($routes));
    }

    public function testRejectsNonRouteValues(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('RouteArray values must be Route instances.');

        $routes = new RouteArray();
        $routes[] = 'invalid';
    }

    public function testRejectsInvalidOffsets(): void
    {
        $routes = new RouteArray();

        foreach (['invalid', -1] as $offset) {
            try {
                self::assertFalse(isset($routes[$offset]));
                self::fail('Expected invalid route offset to throw.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame(
                    'RouteArray offsets must be non-negative integers.',
                    $exception->getMessage()
                );
            }
        }
    }

    private function route(string $path): Route
    {
        return new Route(
            httpMethod: HttpMethod::GET,
            path: $path,
            action: [TestController::class, 'get']
        );
    }
}
