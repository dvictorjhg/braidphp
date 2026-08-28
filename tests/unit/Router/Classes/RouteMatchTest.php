<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Classes\RouteMatch;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\tests\Mock\TestController;

#[CoversClass(RouteMatch::class)]
final class RouteMatchTest extends TestCase
{
    public function testStoresRouteAndParameters(): void
    {
        $route = new Route(
            httpMethod: HttpMethod::GET,
            path: '/hello',
            action: [TestController::class, 'get']
        );
        $match = new RouteMatch($route, ['name' => 'Victor']);

        self::assertSame($route, $match->route);
        self::assertSame(['name' => 'Victor'], $match->params);
    }
}
