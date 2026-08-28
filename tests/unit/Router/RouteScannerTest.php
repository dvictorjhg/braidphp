<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit;

use dvictorjhg\braidphp\Router\RouteScanner;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Classes\RouteArray;
use dvictorjhg\braidphp\tests\Mock\ControllerWithClassRouteAndNoMethods;
use dvictorjhg\braidphp\tests\Mock\ControllerWithoutRoutes;
use dvictorjhg\braidphp\tests\Mock\ControllerWithRoutes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteScanner::class)]
class RouteScannerTest extends TestCase
{
    public function testScanControllerWithoutRoutes(): void
    {
        $route = RouteScanner::scan(ControllerWithoutRoutes::class);

        $this->assertNull($route);
    }

    public function testScanControllerWithRoutes(): void
    {
        $route = RouteScanner::scan(ControllerWithRoutes::class);

        $this->assertInstanceOf(Route::class, $route);
    }

    public function testScanControllerWithClassRouteAndNoMethods(): void
    {
        $route = RouteScanner::scan(ControllerWithClassRouteAndNoMethods::class);

        $this->assertNull($route);
    }

    public function testScanControllerWithMethodRoutesOnly(): void
    {
        $route = RouteScanner::scan(MethodOnlyRouteController::class);

        self::assertInstanceOf(RouteArray::class, $route);
        self::assertCount(1, $route);
    }

    public function testIgnoresNonPublicRouteMethods(): void
    {
        $warningMessage = null;
        \set_error_handler(function (int $severity, string $message) use (&$warningMessage): bool {
            $warningMessage = $message;
            return true;
        });

        try {
            $route = RouteScanner::scan(PrivateRouteController::class);
        } finally {
            \restore_error_handler();
        }

        self::assertNull($route);
        self::assertSame(
            'dvictorjhg\\braidphp\\tests\\unit\\PrivateRouteController::hidden'
            . ' is not public. The route defined for this method will be ignored.',
            $warningMessage
        );
    }
}
