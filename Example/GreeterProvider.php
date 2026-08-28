<?php

namespace dvictorjhg\braidphp\Example;

class GreeterProvider
{
    public function getGreeting(string $name): string
    {
        return "Hello $name!";
    }
}
