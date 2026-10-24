<?php

namespace dvictorjhg\braidphp\tests\Mock;

use dvictorjhg\braidphp\Router\Http\StreamFactory;
use Psr\Http\Message\StreamInterface;

class TestController
{
    public static function get(): StreamInterface
    {
        return StreamFactory::create(\json_encode(['test' => 'test']));
    }
}
