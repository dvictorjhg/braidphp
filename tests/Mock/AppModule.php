<?php

namespace dvictorjhg\braidphp\tests\Mock;

use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Router\HttpModule;

#[Module(
    imports: [
        HttpModule::class
    ],
    controllers: [
        RequestConsumer::class
    ]
)]
class AppModule
{
}
