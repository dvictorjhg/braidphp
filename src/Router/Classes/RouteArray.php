<?php

namespace dvictorjhg\braidphp\Router\Classes;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use TypeError;

/**
 * Indexed collection of Route objects.
 *
 * @implements ArrayAccess<mixed, mixed>
 * @implements IteratorAggregate<int, Route>
 */
final class RouteArray implements ArrayAccess, Countable, IteratorAggregate
{
    /** @var array<int, Route> */
    private array $routes;

    /**
     * Initializes the RouteArray with an optional array of Route objects.
     *
     * @param Route ...$routes A variadic list of Route objects.
     */
    public function __construct(Route ...$routes)
    {
        $this->routes = \array_values($routes);
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->routes[$this->validateOffset($offset)]);
    }

    #[\Override]
    public function offsetGet(mixed $offset): Route
    {
        return $this->routes[$this->validateOffset($offset)];
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof Route) {
            throw new TypeError('RouteArray values must be Route instances.');
        }

        if ($offset === null) {
            $this->routes[] = $value;
            return;
        }

        $this->routes[$this->validateOffset($offset)] = $value;
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->routes[$this->validateOffset($offset)]);
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->routes);
    }

    /** @return ArrayIterator<int, Route> */
    #[\Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->routes);
    }

    private function validateOffset(mixed $offset): int
    {
        if (!\is_int($offset) || $offset < 0) {
            throw new \InvalidArgumentException('RouteArray offsets must be non-negative integers.');
        }

        return $offset;
    }
}
