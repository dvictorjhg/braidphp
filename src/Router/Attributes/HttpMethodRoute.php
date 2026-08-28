<?php

namespace dvictorjhg\braidphp\Router\Attributes;

trait HttpMethodRoute
{
    public function __construct(
        ?string $path = null,
        ?string $pathMatch = 'prefix',
        ?\Closure $matcher = null
    ) {
        parent::__construct(
            httpMethod: static::HTTP_METHOD,
            path: $path,
            pathMatch: $pathMatch,
            matcher: $matcher
        );
    }
}
