<?php

namespace dvictorjhg\braidphp\Example\Providers;

class GreeterProvider
{
    public function getGreeting(string $name): string
    {
        return "Hello $name!";
    }
}
