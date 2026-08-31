<?php

namespace dvictorjhg\braidphp\Example;

use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Example\Modules\GreeterModule;
use dvictorjhg\braidphp\Example\Modules\HealthModule;
use dvictorjhg\braidphp\Router\HttpModule;

#[Module(
    imports: [
        HttpModule::class,
        GreeterModule::class,
        HealthModule::class,
    ],
)]
class AppModule
{
}
