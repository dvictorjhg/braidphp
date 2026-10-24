<?php

declare(strict_types=1);

use PHPInjector\DI\Injector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/fixtures/BenchmarkFeatureValue.php';
require_once __DIR__ . '/fixtures/BenchmarkFeatureService.php';

function benchmarkFeatureValue(): string
{
    $service = Injector::inject(BenchmarkFeatureService::class);
    if (!$service instanceof BenchmarkFeatureService) {
        throw new RuntimeException('Cannot resolve benchmark service graph.');
    }
    return $service->value();
}

/** @return list<MiddlewareInterface> */
function benchmarkFeatureMiddleware(): array
{
    return [
        new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request->withAttribute('benchmark.feature', benchmarkFeatureValue()));
            }
        },
        new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $value = $request->getAttribute('benchmark.feature');
                if (!is_string($value)) {
                    throw new RuntimeException('Missing benchmark request context.');
                }
                return $handler->handle($request)->withHeader('X-Benchmark-Feature', $value);
            }
        },
    ];
}

new Injector([BenchmarkFeatureValue::class, BenchmarkFeatureService::class]);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ && in_array('--self-test', $argv ?? [], true)) {
    $factory = new \dvictorjhg\braidphp\Router\Http\HttpFactory();
    $handler = new class ($factory) implements RequestHandlerInterface {
        public function __construct(private readonly \dvictorjhg\braidphp\Router\Http\HttpFactory $factory)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            if ($request->getAttribute('benchmark.feature') !== 'service:enabled') {
                throw new RuntimeException('Feature request context self-test failed.');
            }
            return $this->factory->createResponse(200);
        }
    };
    $pipeline = new \dvictorjhg\braidphp\Core\MiddlewarePipeline(benchmarkFeatureMiddleware(), $handler);
    $request = $factory->createServerRequest('GET', '/plaintext');
    for ($iteration = 0; $iteration < 2; ++$iteration) {
        if ($pipeline->handle($request)->getHeaderLine('X-Benchmark-Feature') !== 'service:enabled') {
            throw new RuntimeException('Feature response filter self-test failed.');
        }
    }
    if ($request->getAttributes() !== []) {
        throw new RuntimeException('Feature middleware mutated the original request.');
    }
    echo "Feature profile self-test passed.\n";
}
