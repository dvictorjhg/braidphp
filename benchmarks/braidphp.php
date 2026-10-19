<?php

declare(strict_types=1);

use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Router\Attributes\Get;
use dvictorjhg\braidphp\Router\Attributes\Route;
use dvictorjhg\braidphp\Router\Classes\Route as RouteDefinition;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Response;
use dvictorjhg\braidphp\Router\Router;

require_once __DIR__ . '/vendor/autoload.php';

#[Route(path: '/')]
class BenchmarkController
{
    #[Get('/plaintext')]
    public function plaintext(): Response
    {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Hello, World!');
    }

    #[Get('/json')]
    public function json(): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['message' => 'Hello, World!'], JSON_THROW_ON_ERROR)
        );
    }

    #[Get('/users/:id')]
    public function user(Request $request): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['id' => $request->getRouteParam('id')], JSON_THROW_ON_ERROR)
        );
    }
}

#[Module(controllers: [BenchmarkController::class])]
class BenchmarkModule
{
}

$extraRoutes = (int) (getenv('ROUTE_COUNT') ?: '3') - 3;
if ($extraRoutes > 0) {
    $router = new Router();
    for ($routeNumber = 1; $routeNumber <= $extraRoutes; $routeNumber++) {
        $router->setRoutes(new RouteDefinition(
            httpMethod: HttpMethod::GET,
            path: '/unused/' . $routeNumber . '/:id',
            action: [BenchmarkController::class, 'plaintext']
        ));
    }
    $app = new App([Router::class => $router]);
} else {
    $app = new App();
}
$app->bootstrapModule(new BenchmarkModule());

if (getenv('BENCH_NATIVE') === '1') {
    $app->listen('127.0.0.1', getenv('BENCH_PORT') ?: '8091');
    return;
}

$response = $app->handleRequest(new Request());
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}
if (!$response->hasHeader('Content-Type')) {
    header('Content-Type: text/plain');
}
echo $response->getBody();
