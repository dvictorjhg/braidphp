<?php

namespace dvictorjhg\braidphp\Example;

use dvictorjhg\braidphp\Router\Attributes\Get;
use dvictorjhg\braidphp\Router\Attributes\Route;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Response;

#[Route(
    path: '/api'
)]
class GreeterComponent
{
    public function __construct(private GreeterProvider $greeterProvider)
    {
    }

    #[Route(
        httpMethod: HttpMethod::GET->value | HttpMethod::POST->value,
        path: '/hi/:name'
    )]
    public function hi(Request $req): string
    {
        $name = $req->getRouteParam('name') ?? '';
        return "Hi $name!";
    }

    #[Get('/hello/:name')]
    public function greet(Request $req): string
    {
        $name = $req->getRouteParam('name') ?? '';
        return $this->greeterProvider->getGreeting($name);
    }

    #[Get('/hello')]
    public function greetQueryParam(Request $req): Response
    {
        $name = $req->getQueryParams()['name'] ?? null;
        if (\is_string($name) && $name !== '') {
            $greeting = $this->greeterProvider->getGreeting($name);
            return new Response(body: $greeting);
        }
        return new Response(400);
    }
}
