<?php

namespace dvictorjhg\braidphp\Router;

use ReflectionAttribute;
use ReflectionClass;
use dvictorjhg\braidphp\Router\Attributes\Route as RouteAttribute;
use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Classes\RouteArray;

class RouteScanner
{
    /**
     * Scans a class for route attributes and returns a Route object or null if no route attributes are found.
     *
     * @param class-string|object $class The class to scan for route attributes.
     *
      * @return Route|RouteArray|null A Route object if the class has a class route,
      *     a RouteArray object if it only has method routes, or null if no route
     *     attributes are found.
     */
    public static function scan(object|string $class): Route|RouteArray|null
    {
        $reflectionClass = new ReflectionClass($class);
        $className = $reflectionClass->getName();

        /** @var \ReflectionMethod[] */
        $methods = $reflectionClass->getMethods();

        if (empty($methods)) {
            return null;
        }

        /** @var array<string, array<string, ReflectionAttribute<RouteAttribute>>> */
        $methodsRouteAttributes = [];

        foreach ($methods as $method) {
            /** @var ReflectionAttribute<RouteAttribute>[] */
            $methodRouteAttributes = $method->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
            if (empty($methodRouteAttributes)) {
                continue;
            }

            $methodName = $method->getName();
            if (!$method->isPublic()) {
                @\trigger_error(
                    "{$className}::{$methodName} is not public. The route defined for this method will be ignored.",
                    E_USER_WARNING
                );
                continue;
            }

            $methodsRouteAttributes[$methodName] = $methodRouteAttributes;
        }

        if (empty($methodsRouteAttributes)) {
            return null;
        }

        /** @var ReflectionAttribute<RouteAttribute>[] */
        $classRouteAttributes = $reflectionClass->getAttributes(
            RouteAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF
        );
        $classRouteAttribute = $classRouteAttributes[0] ?? null;
        $classRouteAttributeInstance = $classRouteAttribute?->newInstance();
        $classHttpMethod = $classRouteAttributeInstance?->httpMethod;
        $classPath = $classRouteAttributeInstance?->path;
        $classPathMatch = $classRouteAttributeInstance?->pathMatch;
        $classMatcher = $classRouteAttributeInstance?->matcher;
        $methodRoutes = new RouteArray();

        foreach ($methodsRouteAttributes as $methodName => $methodRouteAttributes) {
            foreach ($methodRouteAttributes as $methodRouteAttribute) {
                $methodRouteAttributeInstance = $methodRouteAttribute->newInstance();
                $methodRoutes[] = new Route(
                    httpMethod: $methodRouteAttributeInstance->httpMethod
                        ?? $classHttpMethod,
                    path: $methodRouteAttributeInstance->path
                        ?? $classPath,
                    action: [$className, $methodName],
                    pathMatch: $methodRouteAttributeInstance->pathMatch
                        ?? $classPathMatch
                        ?? 'prefix',
                    matcher: $methodRouteAttributeInstance->matcher
                        ?? $classMatcher
                );
            }
        }

        return $classRouteAttributeInstance === null
            ? $methodRoutes
            : new Route(
                httpMethod: $classHttpMethod,
                path: $classPath,
                pathMatch: $classPathMatch ?? 'prefix',
                matcher: $classMatcher,
                children: $methodRoutes
            );
    }
}
