<?php

namespace dvictorjhg\braidphp\Example;

use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Router\Router;

#[Module(
    providers: [
        Router::class,
        GreeterProvider::class
    ],
    controllers: [GreeterComponent::class]
)]
class AppModule
{
}
