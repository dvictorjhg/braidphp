<?php

namespace dvictorjhg\braidphp\Example\Controllers;

use dvictorjhg\braidphp\Router\Attributes\Get;
use dvictorjhg\braidphp\Router\Attributes\Route;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Example\Providers\HealthProvider;

#[Route(
    path: '/health'
)]
class HealthComponent
{
    public function __construct(private HealthProvider $healthProvider)
    {
    }

    #[Get('/status')]
    public function status(): Response
    {
        return new Response(body: $this->healthProvider->getStatus());
    }
}
