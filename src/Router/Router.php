<?php

namespace dvictorjhg\braidphp\Router;

use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Classes\RouteArray;
use dvictorjhg\braidphp\Router\Classes\RouteMatch;
use dvictorjhg\braidphp\Router\Classes\UrlMatcherResult;

class Router
{
    public function __construct(
        private RouteArray $routes = new RouteArray()
    ) {
    }

    public function getRoutes(): RouteArray
    {
        return $this->routes;
    }

    /** @param Route|RouteArray|array<int, mixed> $routes */
    public function setRoutes(Route|RouteArray|array $routes): void
    {
        if ($routes instanceof Route) {
            $this->routes[] = $routes;
            return;
        }

        foreach ($routes as $route) {
            if (!$route instanceof Route) {
                throw new \InvalidArgumentException('Invalid route provided in setRoutes');
            }
            $this->routes[] = $route;
        }
    }

    public function processRoutes(Request $request): ?RouteMatch
    {
        $requestMethod = $request->getMethod();

        foreach (HttpMethod::cases() as $httpMethod) {
            if ($httpMethod->name === $requestMethod) {
                return $this->navigateRoutes(
                    $requestMethod,
                    $httpMethod->value,
                    $this->routes,
                    $request->getUri()->getPathParts()
                );
            }
        }

        throw new \InvalidArgumentException("Unsupported method '$requestMethod'.");
    }

    /**
     * @param list<string> $pathParts
     * @param array<string, string> $params
     */
    private function navigateRoutes(
        string $requestMethod,
        int $requestMethodValue,
        RouteArray $routes,
        array $pathParts,
        array $params = []
    ): ?RouteMatch {
        foreach ($routes as $route) {
            if (!$this->matchesMethod($route, $requestMethod, $requestMethodValue)) {
                continue;
            }

            $match = $this->matchPath($pathParts, $route);
            if ($match === null) {
                continue;
            }

            $matchedParams = [...$params, ...$match->params];
            $remaining = \array_slice($pathParts, \count($match->consumed));

            if ($remaining === []) {
                return new RouteMatch($route, $matchedParams);
            }

            if ($route->children !== null && \count($route->children) > 0) {
                $childRoute = $this->navigateRoutes(
                    $requestMethod,
                    $requestMethodValue,
                    $route->children,
                    $remaining,
                    $matchedParams
                );
                if ($childRoute !== null) {
                    return $childRoute;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $pathParts
     */
    private function matchPath(array $pathParts, Route $route): ?UrlMatcherResult
    {
        $isRootPath = $route->path === '' || $route->path === '/';
        if (
            $isRootPath
            && $route->pathMatch === 'full'
            && (!empty($pathParts) || !empty($route->children))
        ) {
            return null;
        }

        if ($isRootPath) {
            $matcherResult = new UrlMatcherResult();
        } elseif ($route->matcher !== null) {
            $matcherResult = ($route->matcher)($pathParts, $route);
        } else {
            $matcherResult = self::defaultUrlMatcher($pathParts, $route);
        }

        if ($matcherResult !== null && !($matcherResult instanceof UrlMatcherResult)) {
            throw new RouterException('Route matcher must return UrlMatcherResult.');
        }

        return $matcherResult;
    }

    private function matchesMethod(Route $route, string $requestMethod, int $requestMethodValue): bool
    {
        return $route->httpMethod === null
            || ($route->httpMethod instanceof HttpMethod && $route->httpMethod->name === $requestMethod)
            || (\is_int($route->httpMethod) && ($route->httpMethod & $requestMethodValue) !== 0);
    }

    /**
     * @param list<string> $pathParts
     */
    private static function defaultUrlMatcher(array $pathParts, Route $route): ?UrlMatcherResult
    {
        $routePath = $route->path ?? '';
        if (\str_starts_with($routePath, '/')) {
            $routePath = \substr($routePath, 1);
        }
        $routePathParts = \explode('/', $routePath);
        /** @var array<string, string> $parameters */
        $parameters = [];

        if (
            \count($routePathParts) > \count($pathParts)
            || (\count($routePathParts) < \count($pathParts) && $route->pathMatch === 'full')
        ) {
            return null;
        }

        $routePathPartsLength = \count($routePathParts);
        for ($i = 0; $i < $routePathPartsLength; $i++) {
            $isParameter = \str_starts_with($routePathParts[$i], ':');
            if ($isParameter) {
                $parameterName = \substr($routePathParts[$i], 1);
                $parameters[$parameterName] = \urldecode($pathParts[$i]);
            } elseif ($routePathParts[$i] !== $pathParts[$i]) {
                return null;
            }
        }

        return new UrlMatcherResult(\array_slice($pathParts, 0, $routePathPartsLength), $parameters);
    }
}
