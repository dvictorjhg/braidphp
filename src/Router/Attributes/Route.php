<?php

namespace dvictorjhg\braidphp\Router\Attributes;

use Attribute;
use dvictorjhg\braidphp\Router\Http\HttpMethod;

/**
 * An attribute describing a route path or custom matcher.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class Route
{
    public function __construct(
        public HttpMethod|int|null $httpMethod = null,
        public ?string $path = null,
        public ?string $pathMatch = 'prefix',
        public ?\Closure $matcher = null
    ) {
    }
}
