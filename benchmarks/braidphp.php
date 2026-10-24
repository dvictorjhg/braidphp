<?php

declare(strict_types=1);

use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Router\Classes\Route as RouteDefinition;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Router;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/payloads.php';
require_once __DIR__ . '/fixtures/BenchmarkController.php';
require_once __DIR__ . '/fixtures/BenchmarkModule.php';

if (getenv('BENCH_PROFILE') === 'features') {
    require_once __DIR__ . '/features.php';
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
    $app = new App([
        Router::class => $router,
        ...(getenv('BENCH_PROFILE') === 'features' ? [BenchmarkFeatureValue::class, BenchmarkFeatureService::class] : []),
    ]);
} else {
    $app = new App();
}
$app->bootstrapModule(new BenchmarkModule());

if (getenv('BENCH_PROFILE') === 'features') {
    foreach (benchmarkFeatureMiddleware() as $middleware) {
        $app->addMiddleware($middleware);
    }
}

if (getenv('BENCH_NATIVE') === '1') {
    $app->listen('127.0.0.1', getenv('BENCH_PORT') ?: '8091');
    return;
}

$response = $app->handle(new Request());
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}
if (!$response->hasHeader('Content-Type')) {
    header('Content-Type: ' . BENCHMARK_TEXT_TYPE);
}
echo $response->getBody();
