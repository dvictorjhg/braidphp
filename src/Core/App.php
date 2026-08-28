<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Core;

use PHPInjector\Container\Container;
use PHPInjector\DI\Injector;
use dvictorjhg\braidphp\Core\AppException;
use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Core\Scanners\ModuleScanner;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\Classes\RouteMatch;
use dvictorjhg\braidphp\Router\RouteScanner;
use dvictorjhg\braidphp\Router\Router;

use function PHPInjector\DI\inject;

class App
{
    private ?Injector $injector = null;

    /**
     * @param array<class-string|int|string, mixed>|Container<mixed> $providers
     */
    public function __construct(array|Container $providers = [])
    {
        if (!empty($providers)) {
            $this->injector = new Injector($providers);
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
                'backlog' => 128
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
                $response = null;

                try {
                    $request = Request::fromResource($client);
                    if ($request === null) {
                        continue;
                    }

                    $response = $this->handleRequest($request);
                } catch (\Throwable $exception) {
                    $response = new Response(500, [], 'Server Error: ' . $exception->getMessage());
                } finally {
                    if ($response instanceof Response) {
                        $this->writeResponse($client, $response);
                    }

                    \fclose($client);
                }
            }
        } finally {
            \fclose($server);
        }
    }

    public function handleRequest(Request $request): Response
    {
        $router = $this->inject(Router::class);
        if (!$router instanceof Router) {
            throw new AppException('Router not found');
        }

        $matchedRoute = $router->processRoutes($request);

        if ($matchedRoute instanceof RouteMatch && $matchedRoute->route->action !== null) {
            $request = $request->withRouteParams($matchedRoute->params);
            $result = $this->inject($matchedRoute->route->action, [$request::class => $request]);

            return $result instanceof Response ? $result : $this->responseFromResult($result);
        }

        return new Response(404, [], 'Not Found');
    }

    /**
     * Resolve through this app's configured injector when one exists.
     *
     * @param array<int|string, mixed>|callable|object|string $target
     * @param array<int|string, mixed> $args
     */
    private function inject(array|callable|object|string $target, array $args = []): mixed
    {
        if ($this->injector instanceof Injector) {
            return Injector::inject($target, $args);
        }

        return inject($target, $args);
    }

    private function responseFromResult(mixed $result): Response
    {
        if ($result === null || \is_scalar($result) || \is_object($result) || \is_resource($result)) {
            return new Response(200, [], $result);
        }

        throw new AppException('Route action returned an unsupported result type: ' . \get_debug_type($result));
    }

    /**
     * @param resource $client
     * @param Response $response
     */
    private function writeResponse($client, Response $response): void
    {
        \fwrite($client, (string) $response);
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
            $this->injector = new Injector($providers);
        }
    }

    /** @param Container<mixed> $controllers */
    private function processControllers(Container $controllers): void
    {
        $router = $this->inject(Router::class);

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

            $instance = $this->inject($className, $args);
            if ($instance === null) {
                throw new AppException("Callback failed for [$className].");
            }

            $bootstrap->set($className, $instance);
        }
    }
}
