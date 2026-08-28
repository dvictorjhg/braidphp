<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Classes\RouteArray;
use dvictorjhg\braidphp\Router\Classes\RouteMatch;
use dvictorjhg\braidphp\Router\Classes\UrlMatcherResult;
use dvictorjhg\braidphp\Router\Router;
use dvictorjhg\braidphp\tests\Mock\TestController;

#[CoversClass(Router::class)]
class RouterTest extends TestCase
{
    protected const ROUTES = __DIR__ . '/Router/AppRoutes.php';
    public const HttpMethod ROUTE_METHOD = HttpMethod::GET;
    public const string ROUTE_PATH = '/test';
    public const string ROUTE_CONTROLLER = TestController::class;

    public function testConstructor(): void
    {
        $router = new Router();

        $this->assertInstanceOf(Router::class, $router);
    }

    public function testSetRoutesInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $router = new Router();

        $router->setRoutes(['']);
    }

    public function testSetAndGetRoutes(): void
    {
        $router = new Router();

        $routes = [
            new Route(
                httpMethod: HttpMethod::GET,
                path: self::ROUTE_PATH,
                action: [TestController::class, 'get']
            )
        ];

        $router->setRoutes($routes);
        $routerRoutes = $router->getRoutes();

        $this->assertInstanceOf(RouteArray::class, $routerRoutes);
        $this->assertCount(1, $routerRoutes);
    }

    public function testRouteArrayReplacesRouteAtIndexZero(): void
    {
        $firstRoute = new Route(
            httpMethod: HttpMethod::GET,
            path: '/first',
            action: [TestController::class, 'get']
        );
        $secondRoute = new Route(
            httpMethod: HttpMethod::GET,
            path: '/second',
            action: [TestController::class, 'get']
        );
        $routes = new RouteArray($firstRoute);

        $routes[0] = $secondRoute;

        $this->assertSame($secondRoute, $routes[0]);
        $this->assertCount(1, $routes);
    }

    public function testRouteArrayRejectsNonRouteValues(): void
    {
        $this->expectException(\TypeError::class);

        $routes = new RouteArray();
        $routes[] = 'invalid';
    }

    public function testRouteArrayRejectsInvalidOffsets(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $routes = new RouteArray();
        $routes['invalid'] = new Route(
            httpMethod: HttpMethod::GET,
            path: '/invalid',
            action: [TestController::class, 'get']
        );
    }

    public function testSetAndProcessRoutes(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/greet/victor');
        $router = new Router();

        $routes = [
            new Route(
                path: '',
                children: new RouteArray(
                    new Route(
                        httpMethod: HttpMethod::GET,
                        path: '/hi/:name',
                        action: [TestController::class, 'get']
                    ),
                    new Route(
                        httpMethod: HttpMethod::GET,
                        path: '/greet/:name',
                        action: [TestController::class, 'get']
                    )
                )
            )
        ];

        $router->setRoutes($routes);
        $route = $router->processRoutes($request);

        $this->assertInstanceOf(RouteMatch::class, $route);
        $this->assertSame('/greet/:name', $route->route->path);
        $this->assertSame(['name' => 'victor'], $route->params);
    }

    public function testSetAndProcessMultipleRoutes(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: self::ROUTE_PATH);
        $router = new Router();

        $routes = [
            new Route(
                path: '',
                children: new RouteArray(
                    new Route(
                        httpMethod: HttpMethod::GET,
                        path: '/hi/:name',
                        action: [TestController::class, 'get']
                    ),
                    new Route(
                        httpMethod: HttpMethod::GET,
                        path: '/greet/:name',
                        action: [TestController::class, 'get']
                    )
                )
            )
        ];

        $router->setRoutes($routes);

        $route = new Route(
            httpMethod: HttpMethod::GET,
            path: self::ROUTE_PATH,
            action: [TestController::class, 'get']
        );

        $router->setRoutes($route);

        $route = $router->processRoutes($request);

        $this->assertInstanceOf(RouteMatch::class, $route);
    }

    public function testNoMatchRoute(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: self::ROUTE_PATH);
        $router = new Router();

        $routes = [
            new Route(
                httpMethod: HttpMethod::GET,
                path: '',
                action: [TestController::class, 'get']
            )
        ];

        $router->setRoutes($routes);
        $route = $router->processRoutes($request);

        $this->assertNull($route);
    }

    public function testNoMatchRoutePathMatchFull(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: self::ROUTE_PATH);
        $router = new Router();

        $routes = [
            new Route(
                httpMethod: HttpMethod::GET,
                path: '',
                pathMatch: 'full',
                action: [TestController::class, 'get']
            )
        ];

        $router->setRoutes($routes);
        $route = $router->processRoutes($request);

        $this->assertNull($route);
    }

    public function testRejectsUnsupportedRequestMethod(): void
    {
        $request = new class extends Request {
            public function getMethod(): string
            {
                return 'INVALID';
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported method 'INVALID'.");

        (new Router())->processRoutes($request);
    }

    public function testSkipsRouteWhenPrefixLeavesUnmatchedPath(): void
    {
        $router = new Router();
        $router->setRoutes([
            new Route(
                httpMethod: HttpMethod::POST,
                path: '/other',
                action: [TestController::class, 'get']
            ),
            new Route(
                httpMethod: HttpMethod::GET,
                path: '/test',
                action: [TestController::class, 'get']
            )
        ]);

        self::assertNull($router->processRoutes(new Request(
            method: HttpMethod::GET,
            uri: '/test/extra'
        )));
    }

    public function testUsesCustomMatcherResult(): void
    {
        $router = new Router();
        $router->setRoutes(new Route(
            httpMethod: HttpMethod::GET,
            action: [TestController::class, 'get'],
            matcher: static function (array $pathParts): UrlMatcherResult {
                $pathPart = $pathParts[0] ?? null;
                self::assertIsString($pathPart);
                return new UrlMatcherResult([$pathPart], ['name' => $pathPart]);
            }
        ));

        $match = $router->processRoutes(new Request(method: HttpMethod::GET, uri: '/victor'));

        self::assertInstanceOf(RouteMatch::class, $match);
        self::assertSame(['name' => 'victor'], $match->params);
    }

    public function testRejectsInvalidCustomMatcherResult(): void
    {
        $router = new Router();
        $router->setRoutes(new Route(
            httpMethod: HttpMethod::GET,
            action: [TestController::class, 'get'],
            matcher: static fn(): string => 'invalid'
        ));

        $this->expectException(\dvictorjhg\braidphp\Router\RouterException::class);
        $this->expectExceptionMessage('Route matcher must return UrlMatcherResult.');

        $router->processRoutes(new Request(method: HttpMethod::GET, uri: '/victor'));
    }
}
