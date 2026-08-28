<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Core;

use PHPInjector\Container\Container;
use PHPInjector\DI\Injector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Core\AppException;
use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\HttpModule;
use dvictorjhg\braidphp\Router\Router;
use dvictorjhg\braidphp\tests\Mock\ControllerWithRoutes;

#[CoversClass(App::class)]
final class AppTest extends TestCase
{
    public function testHandleRequestPassesRouteParametersToAction(): void
    {
        new App([Router::class]);
        $router = Injector::inject(Router::class);
        self::assertInstanceOf(Router::class, $router);

        $router->setRoutes(new Route(
            httpMethod: HttpMethod::GET,
            path: '/hello/:name',
            action: [self::class, 'show']
        ));

        $response = (new App())->handleRequest(
            new Request(method: HttpMethod::GET, uri: '/hello/Victor')
        );

        self::assertSame('Victor', (string) $response->getBody());
    }

    public static function show(Request $request): string
    {
        return $request->getRouteParam('name') ?? '';
    }

    public function testRejectsObjectWithoutModuleAttribute(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Module attribute not found');

        (new App())->bootstrapModule(new \stdClass());
    }

    public function testBootstrapsImportsControllersAndBootstrapEntries(): void
    {
        $existing = new BootstrapService();
        $module = new Module(
            imports: [HttpModule::class],
            controllers: [ControllerWithRoutes::class],
            bootstrap: [
                BootstrapService::class => [],
                'existing' => $existing
            ]
        );

        (new App())->bootstrapModule($module);

        self::assertNotNull($module->bootstrap);
        self::assertInstanceOf(BootstrapService::class, $module->bootstrap->get(BootstrapService::class));
        self::assertSame($existing, $module->bootstrap->get('existing'));
    }

    public function testSkipsInvalidImportAndControllerEntries(): void
    {
        $module = new Module(
            imports: ['invalid-import' => 123],
            controllers: ['invalid-controller' => 123]
        );

        (new App([Router::class => new Router()]))->bootstrapModule($module);

        self::assertNotNull($module->imports);
        self::assertNotNull($module->controllers);
    }

    public function testRejectsBootstrapEntryWithNonStringIdentifier(): void
    {
        $bootstrap = new class extends Container {
            public function getIterator(): \ArrayIterator
            {
                return new \ArrayIterator([
                    0 => $this->invalidProvider(),
                    'unused' => null
                ]);
            }

            private function invalidProvider(): mixed
            {
                return [];
            }
        };

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Bootstrap providers must use string identifiers.');

        (new App())->bootstrapModule(new Module(bootstrap: $bootstrap));
    }

    public function testRejectsFailedBootstrapProvider(): void
    {
        $app = new App([BootstrapService::class => null]);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Callback failed for [' . BootstrapService::class . '].');

        $app->bootstrapModule(new Module(bootstrap: [BootstrapService::class => []]));
    }

    public function testReturnsNotFoundWhenRouterIsMissing(): void
    {
        $app = new App([Router::class => null]);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Router not found');

        $app->handleRequest(new Request(method: HttpMethod::GET, uri: '/', body: ''));
    }

    public function testReturnsNotFoundWhenNoRouteMatches(): void
    {
        $app = new App([Router::class => new Router()]);
        $response = $app->handleRequest(new Request(method: HttpMethod::GET, uri: '/', body: ''));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', (string) $response->getBody());
    }

    public function testRejectsUnsupportedRouteActionResult(): void
    {
        $method = new \ReflectionMethod(App::class, 'responseFromResult');

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Route action returned an unsupported result type: array');
        $method->invoke(new App(), []);
    }

    public function testWritesResponseToClientResource(): void
    {
        $client = \fopen('php://temp', 'w+');
        self::assertIsResource($client);
        $method = new \ReflectionMethod(App::class, 'writeResponse');

        try {
            $method->invoke(new App(), $client, new Response(body: 'body'));
            \rewind($client);
            self::assertStringContainsString('body', \stream_get_contents($client));
        } finally {
            \fclose($client);
        }
    }

    public function testRejectsUnbindableListenAddress(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Unable to create socket server:');

        (new App())->listen('invalid.invalid', '1');
    }
}
