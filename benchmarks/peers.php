<?php

declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

$framework = getenv('BENCH_FRAMEWORK');
$extraRoutes = (int) (getenv('ROUTE_COUNT') ?: '3') - 3;
if ($framework !== 'baseline') {
    require_once __DIR__ . '/vendor/autoload.php';
}

$emit = static function (string $body, string $type = 'text/plain', int $status = 200): void {
    http_response_code($status);
    header('Content-Type: ' . $type);
    echo $body;
};

if ($framework === 'slim') {
    $app = \Slim\Factory\AppFactory::create();
    for ($routeNumber = 1; $routeNumber <= $extraRoutes; $routeNumber++) {
        $app->get('/unused/' . $routeNumber . '/{id}', static function ($request, $response) {
            $response->getBody()->write('Hello, World!');
            return $response->withHeader('Content-Type', 'text/plain');
        });
    }
    $app->get('/plaintext', static function ($request, $response) {
        $response->getBody()->write('Hello, World!');
        return $response->withHeader('Content-Type', 'text/plain');
    });
    $app->get('/json', static function ($request, $response) {
        $response->getBody()->write(json_encode(['message' => 'Hello, World!'], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    });
    $app->get('/users/{id}', static function ($request, $response, array $args) {
        $response->getBody()->write(json_encode(['id' => $args['id']], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    });
    $errors = $app->addErrorMiddleware(false, false, false);
    $errors->setErrorHandler(
        \Slim\Exception\HttpNotFoundException::class,
        static function () use ($app) {
            $response = $app->getResponseFactory()->createResponse(404);
            $response->getBody()->write('Not Found');
            return $response->withHeader('Content-Type', 'text/plain');
        }
    );
    $app->run();
} elseif ($framework === 'flight') {
    $app = new \flight\Engine();
    $app->set('flight.log_errors', false);
    for ($routeNumber = 1; $routeNumber <= $extraRoutes; $routeNumber++) {
        $app->route('GET /unused/' . $routeNumber . '/@id', static function () use ($emit): void {
            $emit('Hello, World!');
        });
    }
    $app->route('GET /plaintext', static function () use ($emit): void {
        $emit('Hello, World!');
    });
    $app->route('GET /json', static function () use ($emit): void {
        $emit(json_encode(['message' => 'Hello, World!'], JSON_THROW_ON_ERROR), 'application/json');
    });
    $app->route('GET /users/@id', static function (string $id) use ($emit): void {
        $emit(json_encode(['id' => $id], JSON_THROW_ON_ERROR), 'application/json');
    });
    $app->map('notFound', static function () use ($emit): void {
        $emit('Not Found', 'text/plain', 404);
    });
    $app->start();
} elseif ($framework === 'baseline') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path === '/plaintext') {
        $emit('Hello, World!');
    } elseif ($path === '/json') {
        $emit(json_encode(['message' => 'Hello, World!'], JSON_THROW_ON_ERROR), 'application/json');
    } elseif (preg_match('~^/users/([^/]+)$~', $path, $matches) === 1) {
        $emit(json_encode(['id' => urldecode($matches[1])], JSON_THROW_ON_ERROR), 'application/json');
    } else {
        $emit('Not Found', 'text/plain', 404);
    }
} else {
    throw new RuntimeException('Unknown benchmark framework.');
}
