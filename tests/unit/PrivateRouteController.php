<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit;

use dvictorjhg\braidphp\Router\Attributes\Route as RouteAttribute;

final class PrivateRouteController
{
    #[RouteAttribute(httpMethod: \dvictorjhg\braidphp\Router\Http\HttpMethod::GET, path: '/private')]
    private function hidden(): void
    {
    }

    public function invokeHidden(): void
    {
        $this->hidden();
    }
}
