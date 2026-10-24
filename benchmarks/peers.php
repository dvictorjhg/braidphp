<?php

declare(strict_types=1);

require_once __DIR__ . '/payloads.php';

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

$framework = getenv('BENCH_FRAMEWORK');
$extraRoutes = (int) (getenv('ROUTE_COUNT') ?: '3') - 3;
if ($framework !== 'baseline') {
    require_once __DIR__ . '/vendor/autoload.php';
}

$features = getenv('BENCH_PROFILE') === 'features';
$featureValue = null;
if ($features) {
    require_once __DIR__ . '/features.php';
}

$emit = static function (string $body, string $type = BENCHMARK_TEXT_TYPE, int $status = 200) use (&$featureValue): void {
    http_response_code($status);
    header('Content-Type: ' . $type);
    if ($featureValue !== null) {
        header('X-Benchmark-Feature: ' . $featureValue);
    }
    echo $body;
};

if ($framework === 'slim') {
    $app = \Slim\Factory\AppFactory::create();
    for ($routeNumber = 1; $routeNumber <= $extraRoutes; $routeNumber++) {
        $app->get('/unused/' . $routeNumber . '/{id}', static function ($request, $response) {
            $response->getBody()->write(BENCHMARK_GREETING);
            return $response->withHeader('Content-Type', BENCHMARK_TEXT_TYPE);
        });
    }
    $app->get('/plaintext', static function ($request, $response) {
        $response->getBody()->write(BENCHMARK_GREETING);
        return $response->withHeader('Content-Type', BENCHMARK_TEXT_TYPE);
    });
    $app->get('/json', static function ($request, $response) {
        $response->getBody()->write(json_encode(['message' => BENCHMARK_GREETING], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', BENCHMARK_JSON_TYPE);
    });
    $app->get('/users/{id}', static function ($request, $response, array $args) {
        $response->getBody()->write(json_encode(['id' => $args['id']], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', BENCHMARK_JSON_TYPE);
    });
    $errors = $app->addErrorMiddleware(false, false, false);
    $errors->setErrorHandler(
        \Slim\Exception\HttpNotFoundException::class,
        static function () use ($app) {
            $response = $app->getResponseFactory()->createResponse(404);
            $response->getBody()->write(BENCHMARK_NOT_FOUND);
            return $response->withHeader('Content-Type', BENCHMARK_TEXT_TYPE);
        }
    );
    if ($features) {
        foreach (array_reverse(benchmarkFeatureMiddleware()) as $middleware) {
            $app->add($middleware);
        }
    }
    $app->run();
} elseif ($framework === 'flight') {
    $app = new \flight\Engine();
    $app->set('flight.log_errors', false);
    if ($features) {
        $app->before('start', static function () use (&$featureValue): bool {
            $featureValue = benchmarkFeatureValue();
            return true;
        });
    }
    for ($routeNumber = 1; $routeNumber <= $extraRoutes; $routeNumber++) {
        $app->route('GET /unused/' . $routeNumber . '/@id', static function () use ($emit): void {
            $emit(BENCHMARK_GREETING);
        });
    }
    $app->route('GET /plaintext', static function () use ($emit): void {
        $emit(BENCHMARK_GREETING);
    });
    $app->route('GET /json', static function () use ($emit): void {
        $emit(json_encode(['message' => BENCHMARK_GREETING], JSON_THROW_ON_ERROR), BENCHMARK_JSON_TYPE);
    });
    $app->route('GET /users/@id', static function (string $id) use ($emit): void {
        $emit(json_encode(['id' => $id], JSON_THROW_ON_ERROR), BENCHMARK_JSON_TYPE);
    });
    $app->map('notFound', static function () use ($emit): void {
        $emit(BENCHMARK_NOT_FOUND, BENCHMARK_TEXT_TYPE, 404);
    });
    $app->start();
} elseif ($framework === 'baseline') {
    if ($features) {
        $featureValue = benchmarkFeatureValue();
    }
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path === '/plaintext') {
        $emit(BENCHMARK_GREETING);
    } elseif ($path === '/json') {
        $emit(json_encode(['message' => BENCHMARK_GREETING], JSON_THROW_ON_ERROR), BENCHMARK_JSON_TYPE);
    } elseif (preg_match('~^/users/([^/]+)$~', $path, $matches) === 1) {
        $emit(json_encode(['id' => urldecode($matches[1])], JSON_THROW_ON_ERROR), BENCHMARK_JSON_TYPE);
    } else {
        $emit(BENCHMARK_NOT_FOUND, BENCHMARK_TEXT_TYPE, 404);
    }
} else {
    throw new RuntimeException('Unknown benchmark framework.');
}
