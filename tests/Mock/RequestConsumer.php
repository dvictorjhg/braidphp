<?php

namespace dvictorjhg\braidphp\tests\Mock;

use Psr\Http\Message\StreamInterface;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Stream;
use dvictorjhg\braidphp\Router\Attributes\Get;
use dvictorjhg\braidphp\Router\Attributes\Route;

#[Route(
    path: '/request-consumer'
)]
class RequestConsumer
{
    public function __construct(private Request $request)
    {
    }

    #[Get(
        path: '/get'
    )]
    public function get(): StreamInterface
    {
        return $this->getHttpMethodName();
    }

    #[Route(
        httpMethod: HttpMethod::POST,
        path: '/post'
    )]
    public function post(): StreamInterface
    {
        return $this->getHttpMethodName();
    }

    private function getHttpMethodName(): StreamInterface
    {
        $httpMethod = $this->request->getMethod();
        return Stream::of($httpMethod);
    }
}
