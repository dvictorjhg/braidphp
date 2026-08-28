<?php

namespace dvictorjhg\braidphp\tests\Mock;

use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Attributes\Route;

#[Route(
    httpMethod: HttpMethod::GET,
    path: '/'
)]
class ControllerWithClassRouteAndNoMethods
{
}
