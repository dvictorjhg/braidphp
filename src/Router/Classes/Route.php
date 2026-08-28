<?php

namespace dvictorjhg\braidphp\Router\Classes;

use dvictorjhg\braidphp\Router\Http\HttpMethod;

class Route
{
    /**
     * @param HttpMethod|int|null $httpMethod
     * @param string|null $path
     * @param array{0: class-string, 1: string}|null $action
     * @param string $pathMatch
     * @param \Closure|null $matcher
      * @param RouteArray|null $children
     */
    public function __construct(
        private(set) HttpMethod|int|null $httpMethod = null,
        private(set) string|null $path = null,
        private(set) array|null $action = null,
        private(set) string|null $pathMatch = 'prefix',
        private(set) \Closure|null $matcher = null,
        private(set) RouteArray|null $children = null
    ) {
        $this->validateRoute();
    }

    private function validateRoute(): void
    {
        $message = "Invalid configuration of route '$this->path'";
        if ($this->httpMethod === null && empty($this->children)) {
            throw new RouteException(
                "$message: one of the following must be provided: httpMethod or children."
            );
        }
        if ($this->action === null && empty($this->children)) {
            throw new RouteException(
                "$message: one of the following must be provided: action or children."
            );
        }
        if ($this->path !== null && $this->matcher !== null) {
            throw new RouteException("$message: path and matcher cannot be used together.");
        }
        if ($this->path === null && $this->matcher === null) {
            throw new RouteException("$message: routes must have either a path or a matcher specified.");
        }
    }
}
