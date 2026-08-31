<?php

namespace dvictorjhg\braidphp\Example\Modules;

use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Example\Controllers\HealthComponent;
use dvictorjhg\braidphp\Example\Providers\HealthProvider;

#[Module(
    providers: [
        HealthProvider::class
    ],
    controllers: [
        HealthComponent::class
    ]
)]
class HealthModule
{
}
