<?php

namespace dvictorjhg\braidphp\Example\Providers;

class HealthProvider
{
    public function getStatus(): string
    {
        return 'ok';
    }
}
