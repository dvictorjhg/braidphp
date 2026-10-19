<?php

declare(strict_types=1);

use dvictorjhg\braidphp\Router\Classes\Route;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Router;

require_once __DIR__ . '/vendor/autoload.php';

$iterations = filter_var(getenv('ITERATIONS') ?: '2000', FILTER_VALIDATE_INT);
$rounds = filter_var(getenv('REPEATS') ?: '5', FILTER_VALIDATE_INT);
if ($iterations === false || $iterations < 1 || $rounds === false || $rounds < 1) {
    throw new InvalidArgumentException('ITERATIONS and REPEATS must be positive integers.');
}
$samples = [];
foreach ([3, 100, 1000] as $routeCount) {
    $router = new Router();
    for ($routeNumber = 1; $routeNumber <= $routeCount; $routeNumber++) {
        $router->setRoutes(new Route(
            httpMethod: HttpMethod::GET,
            path: '/items/' . $routeNumber . '/:id',
            action: [Router::class, 'processRoutes']
        ));
    }
    foreach (['parameter' => '/items/' . $routeCount . '/42', 'missing' => '/missing'] as $workload => $uri) {
        $request = new Request(method: HttpMethod::GET, uri: $uri, body: '');
        for ($warmup = 0; $warmup < 100; $warmup++) {
            $router->processRoutes($request);
        }
        for ($round = 1; $round <= $rounds; $round++) {
            $started = hrtime(true);
            for ($iteration = 0; $iteration < $iterations; $iteration++) {
                $match = $router->processRoutes($request);
            }
            $elapsed = hrtime(true) - $started;
            if (($workload === 'parameter' && $match?->params !== ['id' => '42']) || ($workload === 'missing' && $match !== null)) {
                throw new UnexpectedValueException('Router benchmark response mismatch.');
            }
            $samples[] = [
                'routes' => $routeCount,
                'workload' => $workload,
                'round' => $round,
                'iterations' => $iterations,
                'ns_per_dispatch' => $elapsed / $iterations,
            ];
        }
    }
}
$result = [
    'php' => PHP_VERSION,
    'router_sha256' => hash_file('sha256', dirname(__DIR__) . '/src/Router/Router.php'),
    'route_sha256' => hash_file('sha256', dirname(__DIR__) . '/src/Router/Classes/Route.php'),
    'samples' => $samples,
];
if (getenv('SUMMARY')) {
    file_put_contents(getenv('SUMMARY'), json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
}
foreach ([3, 100, 1000] as $routeCount) {
    foreach (['parameter', 'missing'] as $workload) {
        $group = array_values(array_filter($samples, static fn (array $sample): bool => $sample['routes'] === $routeCount && $sample['workload'] === $workload));
        $times = array_column($group, 'ns_per_dispatch');
        sort($times, SORT_NUMERIC);
        $middle = intdiv(count($times), 2);
        $median = count($times) % 2 === 1 ? $times[$middle] : ($times[$middle - 1] + $times[$middle]) / 2;
        printf("%4d routes %-9s median %.3f us/dispatch (%.3f-%.3f)\n", $routeCount, $workload, $median / 1000, min($times) / 1000, max($times) / 1000);
    }
}
