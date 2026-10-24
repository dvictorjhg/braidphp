<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Core;

use PHPInjector\Container\Container;
use PHPInjector\DI\Injector;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Core\Scanners\ModuleScanner;
use dvictorjhg\braidphp\Router\Http\HttpOptions;
use dvictorjhg\braidphp\Router\Http\RequestParser;
use dvictorjhg\braidphp\Router\Http\ResponseEmitter;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\Classes\RouteMatch;
use dvictorjhg\braidphp\Router\RouteScanner;
use dvictorjhg\braidphp\Router\Router;

class App implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middleware = [];

    /**
     * @param array<class-string|int|string, mixed>|Container<mixed> $providers
     */
    public function __construct(
        array|Container $providers = [],
        private readonly HttpOptions $httpOptions = new HttpOptions(),
        private readonly LoggerInterface $logger = new \dvictorjhg\braidphp\Core\ErrorLogLogger()
    ) {
        if (!empty($providers)) {
            new Injector($providers);
        }
    }

    public function bootstrapModule(object $module): void
    {
        if (!$module instanceof Module) {
            $moduleAttribute = ModuleScanner::scan($module);
            if ($moduleAttribute instanceof Module) {
                $module = $moduleAttribute;
            } else {
                throw new AppException("Module attribute not found");
            }
        }

        if ($module->imports) {
            $this->processImports($module->imports);
        }

        if ($module->providers) {
            $this->processProviders($module->providers);
        }

        if ($module->controllers) {
            $this->processControllers($module->controllers);
        }

        if ($module->bootstrap) {
            $this->processBootstrap($module->bootstrap);
        }
    }

    /**
     * Run the application in a single-process server loop.
     */
    public function listen(string $address = '0.0.0.0', string $port = '8000'): void
    {
        $context = \stream_context_create([
            'socket' => [
                'backlog' => $this->httpOptions->backlog
            ]
        ]);

        $server = @\stream_socket_server(
            "tcp://$address:$port",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        if ($server === false) {
            throw new AppException("Unable to create socket server: $errstr ($errno)");
        }

        echo "Server listening on $address:$port...\n";

        try {
            while (($client = @\stream_socket_accept($server, -1)) !== false) {
                $this->serveClient($client);
            }
        } finally {
            \fclose($server);
        }
    }

    /** @param resource $client */
    private function serveClient(mixed $client): void
    {
        $response = null;
        try {
            try {
                $request = RequestParser::fromResource($client, $this->httpOptions);
            } catch (\InvalidArgumentException $exception) {
                $this->logError('Request error', $exception);
                $response = new Response(400, [], 'Bad Request');
                return;
            }
            if ($request !== null) {
                $response = $this->handle($request);
            }
        } catch (\Throwable $exception) {
            $this->logError('Application error', $exception);
            $response = new Response(500, [], 'Internal Server Error');
        } finally {
            try {
                if ($response instanceof ResponseInterface) {
                    ResponseEmitter::write($client, $response, $this->httpOptions);
                }
            } catch (\Throwable $exception) {
                $this->logError('Response write error', $exception);
            } finally {
                \fclose($client);
            }
        }
    }

    private function logError(string $message, \Throwable $exception): void
    {
        try {
            $this->logger->error($message, ['exception' => $exception]);
        } catch (\Throwable) {
            \error_log($message . ': ' . $exception);
        }
    }

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = new class ($this->dispatch(...)) implements RequestHandlerInterface {
            /** @param \Closure(ServerRequestInterface): ResponseInterface $dispatch */
            public function __construct(private readonly \Closure $dispatch)
            {
            }

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->dispatch)($request);
            }
        };

        return (new MiddlewarePipeline($this->middleware, $handler))->handle($request);
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $router = Injector::inject(Router::class);
        if (!$router instanceof Router) {
            throw new AppException('Router not found');
        }

        $matchedRoute = $router->processRoutes($request);

        if ($matchedRoute instanceof RouteMatch && $matchedRoute->route->action !== null) {
            foreach ($matchedRoute->params as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }
            $result = Injector::inject($matchedRoute->route->action, [
                $request::class => $request,
                RequestInterface::class => $request,
                ServerRequestInterface::class => $request
            ]);

            return $result instanceof ResponseInterface ? $result : $this->responseFromResult($result);
        }

        return new Response(404, [], 'Not Found');
    }

    private function responseFromResult(mixed $result): Response
    {
        if ($result === null || \is_scalar($result) || \is_object($result) || \is_resource($result)) {
            return new Response(200, [], $result);
        }

        throw new AppException('Route action returned an unsupported result type: ' . \get_debug_type($result));
    }

    /** @param Container<mixed> $imports */
    private function processImports(Container $imports): void
    {
        foreach ($imports as $module) {
            if (\is_object($module) || (\is_string($module) && \class_exists($module))) {
                $moduleAttribute = ModuleScanner::scan($module);
                if ($moduleAttribute instanceof Module) {
                    $this->bootstrapModule($moduleAttribute);
                }
            }
        }
    }

    /** @param Container<mixed> $providers */
    private function processProviders(Container $providers): void
    {
        if (\count($providers) > 0) {
            new Injector($providers);
        }
    }

    /** @param Container<mixed> $controllers */
    private function processControllers(Container $controllers): void
    {
        $router = Injector::inject(Router::class);

        if ($router instanceof Router) {
            foreach ($controllers as $controller) {
                if (
                    !\is_object($controller)
                    && (!\is_string($controller) || !\class_exists($controller))
                ) {
                    continue;
                }

                $route = RouteScanner::scan($controller);
                if ($route) {
                    $router->setRoutes($route);
                }
            }
        }
    }

    /** @param Container<mixed> $bootstrap */
    private function processBootstrap(Container $bootstrap): void
    {
        foreach ($bootstrap as $className => $object) {
            if (\is_object($object)) {
                continue;
            }

            if (!\is_string($className)) {
                throw new AppException('Bootstrap providers must use string identifiers.');
            }

            $args = \is_array($object) ? $object : [];

            $instance = Injector::inject($className, $args);
            if ($instance === null) {
                throw new AppException("Callback failed for [$className].");
            }

            $bootstrap->set($className, $instance);
        }
    }
}
