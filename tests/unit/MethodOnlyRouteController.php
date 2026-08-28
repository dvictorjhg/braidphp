<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit;

use dvictorjhg\braidphp\Router\Attributes\Route as RouteAttribute;

final class MethodOnlyRouteController
{
    #[RouteAttribute(httpMethod: \dvictorjhg\braidphp\Router\Http\HttpMethod::GET, path: '/method')]
    public function method(): void
    {
    }
}
