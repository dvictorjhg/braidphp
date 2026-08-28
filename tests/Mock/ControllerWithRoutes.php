<?php

namespace dvictorjhg\braidphp\tests\Mock;

use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Attributes\Route;

#[Route(
    path: '/api'
)]
class ControllerWithRoutes
{
    #[Route(
        httpMethod: HttpMethod::GET,
        path: '/hello'
    )]
    public function hello(): void
    {
        echo "hello";
    }

    #[Route(
        httpMethod: HttpMethod::GET,
        path: '/world'
    )]
    public function world(): void
    {
        echo "world";
    }
}
