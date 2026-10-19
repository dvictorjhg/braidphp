# BraidPHP

![BraidPHP](docs/assets/images/brand-header.svg)

[![Latest Version on Packagist][badge-packagist-version]][packagist]
[![Monthly Downloads on Packagist][badge-packagist-downloads]][packagist]
[![Total Downloads on Packagist][badge-packagist-total-downloads]][packagist]
[![PHP Version Required][badge-php-version]][packagist]
[![License][badge-license]](LICENSE)
[![CI][badge-ci]][ci]
[![Codecov][badge-codecov]][codecov]
[![Open Issues][badge-issues]][issues]

BraidPHP is an attribute-driven PHP framework with module composition, routing,
PSR HTTP messages, and a lightweight single-process TCP HTTP runtime.
Dependency injection and provider storage are delegated to the standalone
[`dvictorjhg/php-injector`](https://github.com/dvictorjhg/php-injector) package.

- **Package requirement:** PHP `^8.4`
- **Runtime model:** one blocking process accepts, parses, routes, invokes, and
  writes each request in sequence
- **Main source areas:** [`src/Core`](src/Core) and [`src/Router`](src/Router)
- **Checked-in sample app:** [`Example/`](Example)

## Why to use?

Choose BraidPHP for small HTTP APIs or services where you want explicit module
boundaries, attribute-declared controllers, constructor injection, and a request
lifecycle you can read end to end. It combines those pieces without requiring
a full-stack application framework or a separate server for local execution.

### Characteristics Comparison

| Characteristic | BraidPHP | Slim 4 | Flight 3 |
| --- | --- | --- | --- |
| Primary application style | Attribute controllers and composed modules | Programmatic routes and middleware | Programmatic routes and service helpers |
| Module composition | `imports`, `providers`, `controllers`, `bootstrap` | Application-defined composition | Application-defined composition |
| Dependency injection | Standalone PHPInjector; constructor/action injection | Optional PSR-11 container | Service registration and optional DI integration |
| HTTP messages | Bundled PSR-7 request, response, URI, stream | PSR-7 via an implementation such as slim/psr7 | Framework request/response classes |
| Middleware | No pipeline included | PSR-15 middleware pipeline | Route middleware and hooks |
| Bundled HTTP runtime | Blocking, single-process TCP listener | Uses an external server/SAPI | Uses an external server/SAPI |

Prefer Slim when standardized middleware is central to your application, or
Flight when route/helper-oriented simplicity is a better fit. BraidPHP's native
listener handles one request at a time; it is not an async server or worker pool.
Providers are process-global rather than request-scoped. These are constraints,
not features to hide behind throughput numbers.

### Representative HTTP Benchmarks

Measured freshly on **2026-10-19** with PHP 8.5.11, k6 **2.3.0**, Composer 2.10.3,
and an AMD RYZEN AI MAX+ 392. Tool and benchmark dependency versions were checked
against upstream stable releases before running. Median requests/second across three five-second samples after warmup,
at **16 concurrent users**, with the server and load generator on separate cores.
All frameworks use the same optimized no-dev autoloader; OPcache is on, JIT and
Xdebug are off. These are synthetic routing/response workloads, not real-world
application capacity estimates.

For the shared-server comparison, every framework runs behind the same
single-process PHP server and registers its routes per request. JSON is encoded
per request; the parameter route returns its captured ID; expected 404 responses
are checked as successes. With 100 routes, 97 unused parameterized routes are
registered before the three measured handlers. Optional route caches are off.

| Handler routes | Framework, shared server | Plaintext req/s | JSON req/s | Parameter req/s | 404 req/s | JSON p95 ms |
| ---: | --- | ---: | ---: | ---: | ---: | ---: |
| 3 | BraidPHP | 12,054 | 12,137 | 10,274 | 11,386 | 1.938 |
| 3 | Slim 4.15.3 | 6,903 | 6,511 | 6,225 | 7,399 | 3.602 |
| 3 | Flight 3.19.4 | 15,108 | 12,804 | 10,714 | 12,261 | 2.116 |
| 100 | BraidPHP | 7,203 | 7,801 | 6,768 | 6,956 | 3.072 |
| 100 | Slim 4.15.3 | 2,668 | 2,764 | 2,745 | 2,956 | 7.878 |
| 100 | Flight 3.19.4 | 2,939 | 2,753 | 2,821 | 1,942 | 8.140 |

**Native-runtime results are a separate comparison:** BraidPHP's `App::listen()`
bootstraps once per process and uses its own HTTP parser/emitter instead of the
PHP server. Its results include that lifecycle advantage and different logging.

| Handler routes | BraidPHP native plaintext req/s | JSON req/s | Parameter req/s | 404 req/s | JSON p95 ms |
| ---: | ---: | ---: | ---: | ---: | ---: |
| 3 | 25,515 | 22,340 | 25,766 | 22,669 | 1.207 |
| 100 | 20,197 | 20,246 | 19,829 | 24,980 | 1.394 |

### CPU And Memory

Server-process resources for the **JSON workload at 16 users**. CPU 100% means
one core; CPU us/request is server CPU time divided by completed requests.
CPU and mean RSS are medians across repeats; the sampled RSS peak is the maximum
across repeats. These exclude the load generator and are not whole-host totals.

| Handler routes | Application | CPU % | CPU us/request | Mean RSS MiB | Max sampled RSS MiB |
| ---: | --- | ---: | ---: | ---: | ---: |
| 3 | BraidPHP, shared server | 87.3 | 72.2 | 47.5 | 49.6 |
| 3 | Slim, shared server | 89.8 | 140.1 | 45.8 | 46.5 |
| 3 | Flight, shared server | 84.2 | 66.3 | 49.2 | 52.4 |
| 100 | BraidPHP, shared server | 92.5 | 119.4 | 40.1 | 41.1 |
| 100 | Slim, shared server | 95.2 | 344.9 | 39.0 | 39.5 |
| 100 | Flight, shared server | 95.9 | 350.3 | 38.6 | 39.0 |
| 3 | BraidPHP, native runtime | 42.6 | 21.2 | 36.7 | 36.8 |
| 100 | BraidPHP, native runtime | 64.8 | 34.6 | 36.9 | 36.9 |

RSS includes PHP, extensions, resident shared pages, and state retained from
earlier workloads in the same process. It is **not framework-only or private
memory**. The three-route run also executes one-user workloads before 16-user
workloads; the 100-route run does not. Their RSS values are not an isolated
route-count comparison. Sampling is approximately every 100ms and can miss brief
peaks. Resource windows include k6 launch/exit but exclude server startup and warmup.

All **10,136,660 requests across 180 measured samples** passed status, exact-body,
and content-type checks; the three-route run also tested one concurrent user.
Variability is material: three-route BraidPHP JSON ranged from 7,979 to 12,223
req/s on the shared server. p95 is the median of per-run HTTP percentiles and
excludes connection setup. Connections are not reused, matching the native
listener's behavior. Closed-loop load and short local samples do not prove
production capacity, overload behavior, or universal framework superiority.

See the [benchmark report](benchmarks/REPORT.md) for ranges, low-concurrency
results, the plain-PHP control, and provenance, and the
[benchmark instructions](benchmarks/README.md) to reproduce the measurements.
Database work, injected service graphs, production FPM/worker deployments,
private/heap memory, and keep-alive are not measured.

## Documentation

- **Full guide:** [GitHub Pages documentation](https://dvictorjhg.github.io/braidphp/)
- **Local static entry point:** [`docs/index.html`](docs/index.html)
- **First application walkthrough:** [GitHub Pages first-app guide](https://dvictorjhg.github.io/braidphp/#first-app)
- **Modules and dependency injection:** [GitHub Pages modules guide](https://dvictorjhg.github.io/braidphp/#modules)
- **Routing reference:** [GitHub Pages routing guide](https://dvictorjhg.github.io/braidphp/#routing)
- **Executable recipes:** [JSON API and request handling](https://dvictorjhg.github.io/braidphp/#recipes)
- **HTTP messages and runtime:** [GitHub Pages HTTP guide](https://dvictorjhg.github.io/braidphp/#http), [runtime guide](https://dvictorjhg.github.io/braidphp/#runtime), [operations guide](https://dvictorjhg.github.io/braidphp/#operations)
- **Development and release workflow:** [GitHub Pages development guide](https://dvictorjhg.github.io/braidphp/#development)

## Requirements

- PHP ^8.4
- Composer

## Installation

```bash
composer require dvictorjhg/braidphp
```

## Verified Quick Start

Create one module, one provider, one controller, then start the TCP server:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Router\Attributes\Get;
use dvictorjhg\braidphp\Router\Attributes\Route;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\HttpModule;

final class Greeter
{
    public function greeting(string $name): string
    {
        return "Hello $name!";
    }
}

#[Route(path: '/api')]
final class GreetingController
{
    public function __construct(private Greeter $greeter)
    {
    }

    #[Get('/hello/:name', pathMatch: 'full')]
    public function hello(Request $request): string
    {
        return $this->greeter->greeting($request->getRouteParam('name') ?? '');
    }
}

#[Module(
    imports: [HttpModule::class],
    providers: [Greeter::class],
    controllers: [GreetingController::class],
)]
final class AppModule
{
}

$app = new App();
$app->bootstrapModule(new AppModule());
$app->listen(address: '0.0.0.0', port: '8000');
```

Then request the route:

```text
GET http://127.0.0.1:8000/api/hello/Ada
Hello Ada!
```

For the full empty-directory walkthrough, including `composer.json`, `src/`, and
`index.php`, see the [GitHub Pages first-app guide](https://dvictorjhg.github.io/braidphp/#first-app).

## What The Package Includes

- `#[Module]` application composition with `imports`, `providers`,
  `controllers`, and `bootstrap`
- Optional initial providers passed to `new App([...])` before module bootstrap
- Attribute-driven routing with class prefixes and method-level HTTP route
  attributes
- Supported shortcut attributes: `#[Get]`, `#[Head]`, `#[Post]`, `#[Put]`,
  `#[Delete]`, `#[Connect]`, `#[Options]`, `#[Trace]`, `#[Patch]`
- Programmatic routes via `dvictorjhg\braidphp\Router\Classes\Route`
- Path parameter capture through `Request::getRouteParam()` and
  `Request::getRouteParams()`
- Query-string parsing through `Request::getQueryParams()`
- PSR HTTP message implementations for `Request`, `Response`, `Uri`, and
  `Stream`
- A minimal TCP listener through `App::listen()`

## Runtime Caveats

- `App::listen()` is a **blocking single-process** server loop.
- The example front controller reads `SERVER_ADDRESS` and `SERVER_PORT` from the
  environment and defaults to `0.0.0.0:8000`.
- There is no worker pool, middleware pipeline, or FastCGI/SAPI integration in
  this repository.
- Scale by running multiple processes behind a process manager, reverse proxy,
  or container platform.
- The active PHPInjector is process-global, not request scoped. Do not cache
  request-specific state in shared providers or bootstrap independent apps in
  one process and expect separate containers.
- `handleRequest()` propagates controller exceptions. Only `listen()` catches
  them, logs details server-side, and returns a generic 500 response. Malformed
  requests receive a generic 400 response.
- By default, requests have a five-second total read deadline, a 64 KiB header limit, and
  an 8 MiB body limit. Invalid or duplicate `Content-Length`, truncated bodies,
  and `Transfer-Encoding` are rejected. Chunked requests are not supported.
- Response writes retry partial writes and have a separate five-second default deadline.
  Failed writes are logged and the client is closed without stopping the listener.
- Arrays are not converted to JSON automatically. Return a `Response` with an
  explicit JSON content type and `json_encode(..., JSON_THROW_ON_ERROR)` body.

## Configure HTTP Limits

Pass `HttpOptions` to the application; no vendor edits or global settings are
needed. Existing `new App()` and `Request::fromResource($stream)` calls retain
the defaults. Options are immutable and invalid values throw
`InvalidArgumentException` at construction.

| Option | Default | Meaning |
| --- | --- | --- |
| `maxHeaderBytes` | `65_536` | Entire request line and headers, including CRLF separators; at least 4 and at most `PHP_INT_MAX - 2`. |
| `maxBodyBytes` | `8_388_608` | Maximum declared body length; non-negative. Use `0` to reject nonempty bodies. |
| `readTimeout` | `5.0` | Finite positive seconds for the entire header and body read, not per chunk. |
| `writeTimeout` | `5.0` | Finite positive seconds for the entire response write. |
| `backlog` | `128` | Positive pending-connection queue size passed to the socket; subject to operating-system limits. |

This complete example configures an application and uses the same options for
direct parsing without opening a listening socket:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Router\Http\HttpOptions;
use dvictorjhg\braidphp\Router\Http\Request;

$options = new HttpOptions(
    maxHeaderBytes: 131_072,
    maxBodyBytes: 16_777_216,
    readTimeout: 10.0,
    writeTimeout: 10.0,
    backlog: 256,
);
$app = new App(httpOptions: $options);

$stream = fopen('php://temp', 'w+');
fwrite($stream, "POST / HTTP/1.1\r\nContent-Length:4\r\n\r\ndata");
rewind($stream);
try {
    $request = Request::fromResource($stream, $options);
    echo (string) $request?->getBody();
} finally {
    fclose($stream);
}
```

Expected output:

```text
data
```

Bootstrap modules and call `$app->listen(address: '127.0.0.1', port: '8000')`
in your application's front controller. `RequestParser::fromResource()` accepts
the same optional options argument. HTTP options do not change provider scopes,
route matching, or the single-process model. Configure PHP's `error_log` setting
to choose where server-side errors are written.

## Test Requests Without A Server

This complete example uses the checked-in application, so run it from a repository
clone after `composer install`, not from a package-only installation:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Example\AppModule;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;

$app = new App();
$app->bootstrapModule(new AppModule());
foreach (['/api/hello/Ada', '/health/status', '/missing'] as $uri) {
    $response = $app->handleRequest(new Request(method: HttpMethod::GET, uri: $uri));
    echo $response->getStatusCode() . ' ' . (string) $response->getBody() . PHP_EOL;
}
```

Expected output:

```text
200 Hello Ada!
200 ok
404 Not Found
```

See the [complete JSON recipe](https://dvictorjhg.github.io/braidphp/#recipes)
for decoded path parameters, query validation, dependency injection, strict path
matching, and exception handling.

## Run The Example Application

From the repository root:

```bash
composer install
php Example/index.php
```

In a second terminal:

```bash
curl http://127.0.0.1:8000/api/hello/Ada
# Hello Ada!

curl 'http://127.0.0.1:8000/api/hello?name=Ada'
# Hello Ada!

curl -X POST http://127.0.0.1:8000/api/hi/Ada
# Hi Ada!

curl http://127.0.0.1:8000/health/status
# ok
```

## Development Commands

```bash
php tools/check-docs.php
composer validate --strict
composer check-platform-reqs
composer analyse
composer check-style
composer test
composer test:coverage
composer coverage:check
```

- CI runs the suite in [`.github/workflows/ci.yml`](.github/workflows/ci.yml)
  on PHP 8.4 and 8.5.
- CI also verifies executable documentation on PHP 8.5.
- `composer coverage:check` enforces a minimum **80%** statement coverage from
  the generated Clover XML.
- [`codecov.yml`](codecov.yml) separately configures Codecov project and patch
  targets at **50%**.
- `php tools/check-docs.php` requires PHP DOM and `proc_open`. It executes marked
  HTML examples and non-server README examples in separate PHP processes and
  compares their expected output. Xdebug is disabled only for these child runs.

### Customize Development Tools

Composer forwards arguments after `--`. The coverage checker defaults to
`build/test-results/clover.xml` and 80%; supply both the report and a finite
percentage between 0 and 100 to override them:

```bash
composer test -- --filter RequestParserTest
composer coverage:check -- build/test-results/clover.xml 90
vendor/bin/phpstan analyse --configuration=phpstan.neon
vendor/bin/phpunit --configuration=phpunit.xml --filter AppTest
vendor/bin/phpcs --standard=phpcs.xml src
```

Use your own configuration paths with the same tool flags. Coverage output paths
can be selected with `vendor/bin/phpunit --coverage-clover /tmp/clover.xml`; pass
that report to the checker. Enable Xdebug coverage mode or PCOV for coverage runs.
The CI gate and Codecov targets are separate settings, not runtime limits.

## Containers

The repository includes a PHP image definition in
[`docker/php/php.Dockerfile`](docker/php/php.Dockerfile), a compose file in
[`docker-compose.yml`](docker-compose.yml), and helper launchers in
[`bin/podman-run.sh`](bin/podman-run.sh) and
[`bin/podman-run.ps1`](bin/podman-run.ps1).

Typical local Podman flow:

```bash
./bin/podman-run.sh --environment development --detach
podman exec braidphp-development composer test
./bin/podman-run.sh --action down
```

The default `.env` values expose `SERVER_PORT=8000` and set the base PHP image
tag to `php-8.5.11-cli-trixie`. The Podman launcher appends the environment
name, so its default development image is
`localhost/dvictorjhg/braidphp:php-8.5.11-cli-trixie-development`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md),
and [AI_USE_POLICY.md](AI_USE_POLICY.md).

## Security

See [SECURITY.md](SECURITY.md). Report vulnerabilities privately instead of
opening a public issue.

## Release / Publishing

The next release is **1.1.0**. Finalize its dated entry in
[CHANGELOG.md](CHANGELOG.md), keep [CITATION.cff](CITATION.cff) and the English
and Spanish documentation version labels aligned, and run every development
command above. Review `composer audit` and inspect a `composer archive` build
for source, licensing files, and unwanted local artifacts before tagging.

Publish PHPInjector **1.0.3** first, then create and push the annotated BraidPHP
tag `1.1.0`, publish the GitHub Release, and refresh Packagist. Composer derives
the package version from the Git tag; do not add a `version` field to
`composer.json`. Configuration defaults remain compatible with unconfigured
calls; malformed request framing is now rejected and listener error details are
logged instead of exposed to clients.

## License And Attribution

BraidPHP is released under the [Apache License 2.0](LICENSE). Preserve the
license text, repository copyright, and attribution notices. See
[NOTICE](NOTICE) and [CITATION.cff](CITATION.cff) for attribution details.

[badge-packagist-version]: https://img.shields.io/packagist/v/dvictorjhg/braidphp.svg?style=flat-square
[badge-packagist-downloads]: https://img.shields.io/packagist/dm/dvictorjhg/braidphp.svg?style=flat-square
[badge-packagist-total-downloads]: https://img.shields.io/packagist/dt/dvictorjhg/braidphp.svg?style=flat-square
[badge-php-version]: https://img.shields.io/packagist/php-v/dvictorjhg/braidphp.svg?style=flat-square
[badge-license]: https://img.shields.io/packagist/l/dvictorjhg/braidphp.svg?style=flat-square
[badge-ci]: https://github.com/dvictorjhg/braidphp/actions/workflows/ci.yml/badge.svg?branch=main
[badge-codecov]: https://codecov.io/gh/dvictorjhg/braidphp/branch/main/graph/badge.svg
[badge-issues]: https://img.shields.io/github/issues/dvictorjhg/braidphp.svg?style=flat-square
[packagist]: https://packagist.org/packages/dvictorjhg/braidphp
[ci]: https://github.com/dvictorjhg/braidphp/actions/workflows/ci.yml
[codecov]: https://codecov.io/gh/dvictorjhg/braidphp
[issues]: https://github.com/dvictorjhg/braidphp/issues
