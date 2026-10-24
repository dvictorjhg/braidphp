<?php

declare(strict_types=1);

use dvictorjhg\braidphp\Router\Attributes\Get;
use dvictorjhg\braidphp\Router\Attributes\Route;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Response;

#[Route(path: '/')]
final class BenchmarkController
{
    #[Get('/plaintext')]
    public function plaintext(): Response
    {
        return new Response(200, ['Content-Type' => BENCHMARK_TEXT_TYPE], BENCHMARK_GREETING);
    }

    #[Get('/json')]
    public function json(): Response
    {
        return new Response(
            200,
            ['Content-Type' => BENCHMARK_JSON_TYPE],
            json_encode(['message' => BENCHMARK_GREETING], JSON_THROW_ON_ERROR)
        );
    }

    #[Get('/users/:id')]
    public function user(Request $request): Response
    {
        return new Response(
            200,
            ['Content-Type' => BENCHMARK_JSON_TYPE],
            json_encode(['id' => $request->getAttribute('id')], JSON_THROW_ON_ERROR)
        );
    }
}
