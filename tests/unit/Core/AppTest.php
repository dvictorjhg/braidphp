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
use dvictorjhg\braidphp\Router\Http\HttpOptions;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\HttpModule;
use dvictorjhg\braidphp\Router\Router;
use dvictorjhg\braidphp\tests\Mock\ControllerWithRoutes;
use dvictorjhg\braidphp\tests\Mock\ShortWriteStream;

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

    public function testCompletesShortResponseWrites(): void
    {
        $response = new Response(body: \str_repeat('body', 4096));
        $client = $this->shortWriteClient();
        try {
            (new \ReflectionMethod(App::class, 'writeResponse'))->invoke(new App(), $client, $response);
            self::assertSame((string) $response, ShortWriteStream::$written);
        } finally {
            \fclose($client);
            \stream_wrapper_unregister('shortwrite');
        }
    }

    public function testRejectsStalledResponseWrites(): void
    {
        $client = $this->shortWriteClient();
        ShortWriteStream::$stall = true;
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Failed to write response to client.');
        try {
            (new \ReflectionMethod(App::class, 'writeResponse'))->invoke(new App(), $client, new Response());
        } finally {
            \fclose($client);
            \stream_wrapper_unregister('shortwrite');
        }
    }

    public function testSocketWriteDoesNotWaitForeverForSlowReader(): void
    {
        $sockets = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);
        $this->expectException(AppException::class);
        try {
            (new \ReflectionMethod(App::class, 'writeResponse'))->invoke(
                new App(httpOptions: new HttpOptions(writeTimeout: 0.02)),
                $sockets[0],
                new Response(body: \str_repeat('a', 8_388_608))
            );
        } finally {
            \fclose($sockets[0]);
            \fclose($sockets[1]);
        }
    }

    public function testMalformedRequestGetsBadRequestAndIsLogged(): void
    {
        $log = '';
        $response = $this->exchange(new App(), "POST / HTTP/1.1\r\nContent-Length: nope\r\n\r\n", $log);
        self::assertStringStartsWith('HTTP/1.1 400 Bad Request', $response);
        self::assertStringNotContainsString('Invalid or duplicate', $response);
        self::assertStringContainsString('Request error:', $log);
    }

    public function testListenerUsesCustomRequestLimits(): void
    {
        $app = new App(httpOptions: new HttpOptions(maxBodyBytes: 0));
        $response = $this->exchange($app, "POST / HTTP/1.1\r\nContent-Length:4\r\n\r\ndata");
        self::assertStringStartsWith('HTTP/1.1 400 Bad Request', $response);
    }

    public function testApplicationFailureIsSanitizedAndLogged(): void
    {
        $app = new class extends App {
            public function handleRequest(Request $request): Response
            {
                throw new \InvalidArgumentException('private application details');
            }
        };
        $log = '';
        $response = $this->exchange($app, "GET / HTTP/1.1\r\n\r\n", $log);
        self::assertStringStartsWith('HTTP/1.1 500 Internal Server Error', $response);
        self::assertStringNotContainsString('private application details', $response);
        self::assertStringContainsString('Application error:', $log);
        self::assertStringContainsString('private application details', $log);
    }

    public function testServesValidRequestAndClosesConnection(): void
    {
        $response = $this->exchange(new App([Router::class => new Router()]), "GET / HTTP/1.1\r\n\r\n");
        self::assertStringStartsWith('HTTP/1.1 404 Not Found', $response);
    }

    public function testEmptyConnectionDoesNotProduceResponse(): void
    {
        self::assertSame('', $this->exchange(new App(), ''));
    }

    public function testDisconnectedClientIsLoggedWithoutEscapingHandler(): void
    {
        $app = new class extends App {
            public function handleRequest(Request $request): Response
            {
                return new Response();
            }
        };
        $sockets = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);
        \fwrite($sockets[1], "GET / HTTP/1.1\r\n\r\n");
        \fclose($sockets[1]);
        try {
            $log = $this->captureErrors(static function () use ($app, $sockets): void {
                (new \ReflectionMethod(App::class, 'serveClient'))->invoke($app, $sockets[0]);
            });
            self::assertStringContainsString('Response write error:', $log);
            self::assertFalse(\is_resource($sockets[0]));
        } finally {
            if (\is_resource($sockets[0])) {
                \fclose($sockets[0]);
            }
        }
    }

    /** @return resource */
    private function shortWriteClient(): mixed
    {
        ShortWriteStream::$written = '';
        ShortWriteStream::$stall = false;
        ShortWriteStream::$pause = false;
        self::assertTrue(\stream_wrapper_register('shortwrite', ShortWriteStream::class));
        $client = \fopen('shortwrite://response', 'w');
        self::assertIsResource($client);
        return $client;
    }

    private function exchange(App $app, string $request, string &$log = ''): string
    {
        $sockets = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);
        try {
            if ($request !== '') {
                \fwrite($sockets[1], $request);
            }
            \stream_socket_shutdown($sockets[1], STREAM_SHUT_WR);
            $log = $this->captureErrors(static function () use ($app, $sockets): void {
                (new \ReflectionMethod(App::class, 'serveClient'))->invoke($app, $sockets[0]);
            });
            self::assertFalse(\is_resource($sockets[0]));
            $response = \stream_get_contents($sockets[1]);
            self::assertIsString($response);
            return $response;
        } finally {
            if (\is_resource($sockets[0])) {
                \fclose($sockets[0]);
            }
            \fclose($sockets[1]);
        }
    }

    private function captureErrors(\Closure $action): string
    {
        $path = \tempnam(\sys_get_temp_dir(), 'braid-errors-');
        self::assertIsString($path);
        $previous = \ini_set('error_log', $path);
        self::assertIsString($previous);
        try {
            $action();
            $log = \file_get_contents($path);
            self::assertIsString($log);
            return $log;
        } finally {
            \ini_set('error_log', $previous);
            \unlink($path);
        }
    }
}
