<?php

namespace dvictorjhg\braidphp\Example\Modules;

use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Example\Providers\GreeterProvider;

#[Module(
    providers: [
        GreeterProvider::class
    ]
)]
class GreeterProviderModule
{
}
