<?php

namespace dvictorjhg\braidphp\tests\Mock;

use dvictorjhg\braidphp\Router\Http\Stream;
use Psr\Http\Message\StreamInterface;

class TestController
{
    public static function get(): StreamInterface
    {
        return Stream::of(\json_encode(['test' => 'test']));
    }
}
