<?php

namespace dvictorjhg\braidphp\Example\Modules;

use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Example\Controllers\GreeterComponent;
use dvictorjhg\braidphp\Example\Modules\GreeterProviderModule;

#[Module(
    imports: [
        GreeterProviderModule::class
    ],
    controllers: [
        GreeterComponent::class
    ]
)]
class GreeterModule
{
}
