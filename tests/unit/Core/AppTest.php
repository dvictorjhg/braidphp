<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Core;

use PHPInjector\Container\Container;
use PHPInjector\DI\Injector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Core\AppException;
use dvictorjhg\braidphp\Core\MiddlewarePipeline;
use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\HttpOptions;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\HttpModule;
use dvictorjhg\braidphp\Router\Router;
use dvictorjhg\braidphp\tests\Mock\ControllerWithRoutes;

#[CoversClass(App::class)]
#[CoversClass(MiddlewarePipeline::class)]
final class AppTest extends TestCase
{
    public function testListenerReportsExceptionsThroughPsrLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Request error',
            self::callback(static function (array $context): bool {
                return $context['exception'] instanceof \InvalidArgumentException;
            })
        );
        $response = $this->exchange(new App(logger: $logger), "POST / HTTP/1.1\r\nContent-Length: nope\r\n\r\n");
        self::assertStringStartsWith('HTTP/1.1 400 Bad Request', $response);
    }

    public function testBrokenLoggerCannotPreventSanitizedListenerResponse(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willThrowException(new AppException('logging backend failed'));
        $log = '';
        $response = $this->exchange(new App(logger: $logger), "POST / HTTP/1.1\r\nContent-Length: nope\r\n\r\n", $log);
        self::assertStringStartsWith('HTTP/1.1 400 Bad Request', $response);
        self::assertStringContainsString('Request error:', $log);
        self::assertStringNotContainsString('logging backend failed', $response);
    }

    public function testMiddlewareOrderAndRequestChangesAcrossRepeatedRequests(): void
    {
        $events = [];
        $app = new App([Router::class => new Router()]);
        $router = Injector::inject(Router::class);
        self::assertInstanceOf(Router::class, $router);
        $router->setRoutes(new Route(
            httpMethod: HttpMethod::GET,
            path: '/hello/:name',
            action: [self::class, 'showServerRequest']
        ));
        foreach (['outer', 'inner'] as $name) {
            self::assertSame($app, $app->addMiddleware($this->middleware(
                static function (
                    ServerRequestInterface $request,
                    RequestHandlerInterface $handler
                ) use (
                    &$events,
                    $name
                ): ResponseInterface {
                    $events[] = "$name before";
                    $response = $handler->handle($request->withAttribute('user', 'Ada'));
                    $events[] = "$name after";
                    return $response->withAddedHeader('X-Middleware', $name);
                }
            )));
        }

        $request = new Request(uri: '/hello/Victor', body: '');
        for ($iteration = 0; $iteration < 2; ++$iteration) {
            $response = $app->handle($request);
            self::assertSame('Victor:Ada', (string) $response->getBody());
            self::assertSame(['inner', 'outer'], $response->getHeader('X-Middleware'));
        }
        self::assertSame(\array_merge(
            ['outer before', 'inner before', 'inner after', 'outer after'],
            ['outer before', 'inner before', 'inner after', 'outer after']
        ), $events);
        self::assertSame([], $request->getAttributes());
    }

    public static function showServerRequest(ServerRequestInterface $request): string
    {
        $name = $request->getAttribute('name');
        $user = $request->getAttribute('user');
        self::assertIsString($name);
        self::assertIsString($user);
        return $name . ':' . $user;
    }

    public function testMiddlewareCanShortCircuitBeforeRouterResolution(): void
    {
        $expected = new Response(401, [], 'Unauthorized');
        $app = new App([Router::class => null]);
        $app->addMiddleware($this->middleware(
            static fn (): ResponseInterface => $expected
        ));
        $app->addMiddleware($this->middleware(static function (): never {
            self::fail('Later middleware must not execute after a short circuit.');
        }));

        self::assertSame($expected, $app->handle(new Request(body: '')));
        self::assertStringStartsWith('HTTP/1.1 401 Unauthorized', $this->exchange($app, "GET / HTTP/1.1\r\n\r\n"));
    }

    public function testDownstreamHandlerCanBeInvokedMoreThanOnce(): void
    {
        $calls = 0;
        $app = new App([Router::class => new Router()]);
        $app->addMiddleware($this->middleware(
            static function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                $handler->handle($request);
                return $handler->handle($request);
            }
        ));
        $app->addMiddleware($this->middleware(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ) use (
                &$calls
            ): ResponseInterface {
                ++$calls;
                return $handler->handle($request);
            }
        ));

        self::assertSame(404, $app->handle(new Request(body: ''))->getStatusCode());
        self::assertSame(2, $calls);
    }

    public function testOuterMiddlewareCanConvertDispatchExceptionsToResponses(): void
    {
        $app = new App([Router::class => null]);
        $app->addMiddleware($this->middleware(
            static function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                try {
                    return $handler->handle($request);
                } catch (AppException) {
                    return new Response(503, [], 'Unavailable');
                }
            }
        ));

        self::assertSame(503, $app->handle(new Request(body: ''))->getStatusCode());
    }

    public function testListenerSanitizesUncaughtMiddlewareExceptions(): void
    {
        $app = new App();
        $app->addMiddleware($this->middleware(static function (): never {
            throw new AppException('private middleware details');
        }));
        $log = '';
        $response = $this->exchange($app, "GET / HTTP/1.1\r\n\r\n", $log);
        self::assertStringStartsWith('HTTP/1.1 500 Internal Server Error', $response);
        self::assertStringNotContainsString('private middleware details', $response);
        self::assertStringContainsString('private middleware details', $log);
    }

    public function testHandlesForeignServerRequestsAndRouteActionResponses(): void
    {
        $app = new App([Router::class => new Router()]);
        $router = Injector::inject(Router::class);
        self::assertInstanceOf(Router::class, $router);
        $router->setRoutes(new Route(
            httpMethod: HttpMethod::GET,
            path: '/hello/:name',
            action: [self::class, 'showServerRequest']
        ));
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/hello/Victor');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);
        $request->expects(self::once())->method('withAttribute')->with('name', 'Victor')->willReturnSelf();
        $request->method('getAttribute')->willReturnMap([['name', null, 'Victor'], ['user', null, 'Ada']]);

        self::assertSame('Victor:Ada', (string) $app->handle($request)->getBody());

        $response = $this->createStub(ResponseInterface::class);
        $router->setRoutes(new Route(
            httpMethod: HttpMethod::GET,
            path: '/foreign',
            action: [self::class, 'foreignResponse']
        ));
        self::assertSame($response, $app->handle(
            (new Request(uri: '/foreign', body: ''))->withAttribute('response', $response)
        ));
    }

    public static function foreignResponse(ServerRequestInterface $request): ResponseInterface
    {
        $response = $request->getAttribute('response');
        self::assertInstanceOf(ResponseInterface::class, $response);
        return $response;
    }

    public function testHandlerAndListenerPreserveForeignMiddlewareResponses(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(202);
        $response->method('getHeaders')->willReturn(['X-Test' => ['yes']]);
        $response->method('getBody')->willReturn((new Response(body: 'foreign'))->getBody());
        $response->method('getProtocolVersion')->willReturn('1.0');
        $response->method('getReasonPhrase')->willReturn('Custom');
        $app = new App();
        $app->addMiddleware($this->middleware(static fn (): ResponseInterface => $response));

        self::assertSame($response, $app->handle(new Request(body: '')));
        $wire = $this->exchange($app, "GET / HTTP/1.1\r\n\r\n");
        self::assertStringStartsWith('HTTP/1.0 202 Custom', $wire);
        self::assertStringContainsString("X-Test: yes\r\n", $wire);
        self::assertStringEndsWith('foreign', $wire);
    }

    /** @param \Closure(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $process */
    private function middleware(\Closure $process): MiddlewareInterface
    {
        return new class ($process) implements MiddlewareInterface {
            /** @param \Closure(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $process */
            public function __construct(private readonly \Closure $process)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return ($this->process)($request, $handler);
            }
        };
    }

    public function testHandlerPassesRouteParametersToAction(): void
    {
        self::assertInstanceOf(App::class, new App([Router::class]));
        $router = Injector::inject(Router::class);
        self::assertInstanceOf(Router::class, $router);

        $router->setRoutes(new Route(
            httpMethod: HttpMethod::GET,
            path: '/hello/:name',
            action: [self::class, 'show']
        ));

        $response = (new App())->handle(
            new Request(method: HttpMethod::GET, uri: '/hello/Victor')
        );

        self::assertSame('Victor', (string) $response->getBody());
    }

    public static function show(Request $request): string
    {
        $name = $request->getAttribute('name');
        self::assertIsString($name);
        return $name;
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

        $app->handle(new Request(method: HttpMethod::GET, uri: '/', body: ''));
    }

    public function testReturnsNotFoundWhenNoRouteMatches(): void
    {
        $app = new App([Router::class => new Router()]);
        $response = $app->handle(new Request(method: HttpMethod::GET, uri: '/', body: ''));

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

    #[RunInSeparateProcess]
    public function testListenerServesConnectionAndClosesSocketWhenInterrupted(): void
    {
        if (!\function_exists('pcntl_alarm')) {
            self::markTestSkipped('The bounded listener test requires pcntl.');
        }
        $reservation = \stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($reservation);
        $address = \stream_socket_get_name($reservation, false);
        self::assertIsString($address);
        $port = \substr($address, \strrpos($address, ':') + 1);
        \fclose($reservation);

        $client = null;
        $previousAsyncSignals = \pcntl_async_signals(true);
        $previousHandler = \pcntl_signal_get_handler(SIGALRM);
        \pcntl_signal(SIGALRM, static function (): never {
            throw new AppException('Listener test deadline reached.');
        });
        $bufferLevel = \ob_get_level();
        \ob_start(static function (string $output) use ($address, &$client): string {
            if (\str_contains($output, 'Server listening on')) {
                $client = \stream_socket_client('tcp://' . $address, timeout: 1);
                self::assertIsResource($client);
                \stream_set_timeout($client, 1);
                \fwrite($client, "GET / HTTP/1.1\r\n\r\n");
                \stream_socket_shutdown($client, STREAM_SHUT_WR);
            }
            return $output;
        }, 1);
        $this->expectOutputString("Server listening on 127.0.0.1:$port...\n");

        try {
            \pcntl_alarm(2);
            try {
                (new App([Router::class => new Router()]))->listen('127.0.0.1', $port);
                self::fail('Expected the listener deadline to interrupt accept.');
            } catch (AppException $exception) {
                self::assertSame('Listener test deadline reached.', $exception->getMessage());
            }
            self::assertIsResource($client);
            $response = \stream_get_contents($client);
            self::assertIsString($response);
            self::assertStringStartsWith('HTTP/1.1 404 Not Found', $response);
        } finally {
            \pcntl_alarm(0);
            \pcntl_signal(SIGALRM, $previousHandler);
            \pcntl_async_signals($previousAsyncSignals);
            if (\is_resource($client)) {
                \fclose($client);
            }
            while (\ob_get_level() > $bufferLevel) {
                \ob_end_flush();
            }
        }
    }

    public function testRejectsUnbindableListenAddress(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Unable to create socket server:');

        (new App())->listen('invalid.invalid', '1');
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
            public function handle(ServerRequestInterface $request): ResponseInterface
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
            public function handle(ServerRequestInterface $request): ResponseInterface
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
