<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit;

use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Classes\RouteArray;
use dvictorjhg\braidphp\tests\Mock\TestController;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    private const HttpMethod ROUTE_METHOD = HttpMethod::GET;
    private const string ROUTE_PATH = '/test';
    /**
     * @var class-string
     */
    private const string ROUTE_CONTROLLER = TestController::class;

    public function testRouteConstructor(): void
    {
        $route = new Route(
            httpMethod: self::ROUTE_METHOD,
            path: self::ROUTE_PATH,
            action: [self::ROUTE_CONTROLLER, 'get']
        );

        $this->assertInstanceOf(Route::class, $route);
    }

    public function testRouteExceptionOneOfTheFollowingMustBeProvidedHttpMethodOrChildren(): void
    {
        $this->expectExceptionMessage(
            "Invalid configuration of route '" . self::ROUTE_PATH
            . "': one of the following must be provided: httpMethod or children."
        );

        new Route(path: self::ROUTE_PATH);
    }

    public function testRouteExceptionOneOfTheFollowingMustBeProvided(): void
    {
        $this->expectExceptionMessage(
            "Invalid configuration of route '" .
                self::ROUTE_PATH .
                "': one of the following must be provided: action or children."
        );

        new Route(self::ROUTE_METHOD, self::ROUTE_PATH);
    }

    public function testRouteExceptionPathAndMatcherCannotBeUsedTogether(): void
    {
        $this->expectExceptionMessage(
            "Invalid configuration of route '': path and matcher cannot be used together."
        );

        new Route(
            httpMethod: self::ROUTE_METHOD,
            path: '',
            action: [self::ROUTE_CONTROLLER, 'get'],
            matcher: fn(): bool => true
        );
    }

    public function testRouteExceptionRoutesMustHaveEitherAPathOrAMatcherSpecified(): void
    {
        $this->expectExceptionMessage(
            "Invalid configuration of route '': routes must have either a path or a matcher specified."
        );

        new Route(
            httpMethod: self::ROUTE_METHOD,
            action: [self::ROUTE_CONTROLLER, 'get']
        );
    }

    public function testGet(): void
    {
        $route = new Route(
            httpMethod: self::ROUTE_METHOD,
            path: self::ROUTE_PATH,
            action: [self::ROUTE_CONTROLLER, 'get']
        );

        $this->assertEquals(self::ROUTE_PATH, $route->path);
    }
}
