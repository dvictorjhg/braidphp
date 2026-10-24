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
PSR-7 messages, PSR-15 middleware, PSR-17 factories, PSR-3 listener logging,
and a bundled single-process TCP HTTP listener.
Dependency injection and provider storage are delegated to the standalone
[`dvictorjhg/php-injector`](https://github.com/dvictorjhg/php-injector) package.

[Quick start](#verified-quick-start) | [Documentation](docs/index.html) | [Benchmarks](#recorded-http-benchmarks) | [Contributing](CONTRIBUTING.md)

- **Package requirement:** PHP `~8.4.27 || ^8.5.12`
- **Native listener:** one blocking process accepts, parses, routes, invokes, and
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
| Middleware | PSR-15 middleware pipeline | PSR-15 middleware pipeline | Route middleware and hooks |
| Bundled HTTP runtime | Blocking, single-process TCP listener | Uses an external server/SAPI | Uses an external server/SAPI |

Prefer Slim for its broader middleware ecosystem and external-server integration, or
Flight when route/helper-oriented simplicity is a better fit. BraidPHP's native
listener handles one request at a time; it is not an async server or worker pool.
Providers are process-global rather than request-scoped. These are constraints,
not features to hide behind throughput numbers.

### Recorded HTTP Benchmarks

Recorded on **2026-10-24** with PHP 8.5.12, k6 **2.3.0**, Composer 2.10.3,
and an AMD RYZEN AI MAX+ 392. Tool and benchmark dependency versions were checked
against upstream stable releases before running. Median requests/second across three five-second samples after warmup,
at **16 concurrent users**, with the server and load generator on separate cores.
All frameworks use the same optimized no-dev autoloader; OPcache is on, JIT and
Xdebug are off. These are synthetic routing/response workloads, not real-world
application capacity estimates.

These tables measure the **BraidPHP 1.2.0 candidate**. All four datasets record
the same 54 source and executable harness hashes, verified against the measured
checkout. No before/after speedup is inferred from these separately timed runs.

For the shared-server comparison, every framework runs behind the same
single-process PHP server and registers its routes per request. JSON is encoded
per request; the parameter route returns its captured ID; expected 404 responses
are checked as successes. With 100 routes, 97 unused parameterized routes are
registered before the three measured handlers. Optional route caches are off.

| Handler routes | Framework, shared server | Plaintext req/s (higher is better) | JSON req/s (higher is better) | Parameter req/s (higher is better) | 404 req/s (higher is better) | JSON p95 ms (lower is better) |
| ---: | --- | ---: | ---: | ---: | ---: | ---: |
| 3 | BraidPHP | 9,055 | 8,760 | 10,180 | 10,528 | 2.837 |
| 3 | Slim 4.15.3 | 6,219 | 6,719 | 6,330 | 7,363 | 3.616 |
| 3 | Flight 3.19.4 | 11,506 | 11,969 | 12,150 | 14,370 | 1.926 |
| 100 | BraidPHP | 5,055 | 6,159 | 5,986 | 7,159 | 4.383 |
| 100 | Slim 4.15.3 | 2,785 | 2,886 | 3,137 | 3,241 | 6.863 |
| 100 | Flight 3.19.4 | 3,100 | 2,811 | 2,530 | 1,906 | 7.452 |

These are the `bare` profile. A separate `features` profile adds equivalent
request/response filters and the same two-class PHPInjector service graph to
every target. BraidPHP and Slim use the same two PSR-15 middleware objects;
Flight uses native hooks. Payloads, routing, and connection policy remain equal.

| Handler routes | Framework, shared server with features | JSON req/s (higher is better) | JSON p95 ms (lower is better) |
| ---: | --- | ---: | ---: |
| 3 | BraidPHP | 8,551 | 2.942 |
| 3 | Slim 4.15.3 | 5,605 | 4.028 |
| 3 | Flight 3.19.4 | 8,989 | 2.589 |
| 100 | BraidPHP | 5,753 | 3.313 |
| 100 | Slim 4.15.3 | 2,477 | 7.718 |
| 100 | Flight 3.19.4 | 2,528 | 8.212 |

**Native-runtime results are a separate comparison:** BraidPHP's `App::listen()`
bootstraps once per process and uses its own HTTP parser/emitter instead of the
PHP server. Its results include that lifecycle advantage and different logging.

| Handler routes | BraidPHP native plaintext req/s (higher is better) | JSON req/s (higher is better) | Parameter req/s (higher is better) | 404 req/s (higher is better) | JSON p95 ms (lower is better) |
| ---: | ---: | ---: | ---: | ---: | ---: |
| 3 | 25,082 | 21,713 | 24,161 | 23,371 | 1.311 |
| 100 | 22,040 | 23,931 | 17,778 | 22,511 | 0.981 |

The native table is the bare profile; the report also records native feature
results. Neither table isolates routing performance from runtime lifecycle.

### CPU And Memory

Server-process resources for the **bare JSON workload at 16 users**. CPU 100% means
one core; CPU us/request is server CPU time divided by completed requests.
CPU and mean RSS are medians across repeats; the sampled RSS peak is the maximum
across repeats. These exclude the load generator and are not whole-host totals.

Lower resource cost is better for equivalent work and process history. CPU %
alone is not a ranking: low utilization can also mean fewer requests are served.

| Handler routes | Application | CPU % (context-dependent) | CPU us/request (lower is better) | Mean RSS MiB (lower is better) | Max sampled RSS MiB (lower is better) |
| ---: | --- | ---: | ---: | ---: | ---: |
| 3 | BraidPHP, shared server | 88.0 | 101.1 | 41.3 | 42.9 |
| 3 | Slim, shared server | 91.5 | 134.2 | 40.7 | 41.7 |
| 3 | Flight, shared server | 85.3 | 72.2 | 42.5 | 44.4 |
| 100 | BraidPHP, shared server | 93.9 | 153.5 | 39.7 | 40.6 |
| 100 | Slim, shared server | 96.4 | 331.5 | 39.4 | 39.7 |
| 100 | Flight, shared server | 95.1 | 339.7 | 38.7 | 39.1 |
| 3 | BraidPHP, native runtime | 45.9 | 20.6 | 37.0 | 37.1 |
| 100 | BraidPHP, native runtime | 73.5 | 31.3 | 37.0 | 37.0 |

RSS includes PHP, extensions, resident shared pages, and state retained from
earlier workloads in the same process. It is **not framework-only or private
memory**. Both datasets use 16 users with the same workload order, but their RSS values are not an isolated
route-count comparison. Sampling is approximately every 100ms and can miss brief
peaks. Resource windows include k6 launch/exit but exclude server startup and warmup.

All **15,488,351 requests across 240 measured samples** passed status, exact-body,
content-type, and feature-header checks across both profiles and route counts.
All datasets use 16 users.
Variability is material: bare three-route BraidPHP JSON ranged from 6,808 to 12,026
req/s on the shared server. p95 is the median of per-run HTTP percentiles and
excludes connection setup. Connections are not reused, matching the native
listener's behavior. Closed-loop load and short local samples do not prove
production capacity, overload behavior, or universal framework superiority.

See the [benchmark report](benchmarks/REPORT.md) for ranges, feature-profile
results, the plain-PHP control, and provenance, and the
[benchmark instructions](benchmarks/README.md) to reproduce the measurements.
The feature profile exercises middleware, request attributes, and service
resolution. All six factories, upload movement, server-request state, and logger
injection/fallback are covered by the contract suite, not assigned only to
BraidPHP's throughput workload. Database work, upload/logging throughput,
production FPM/workers, private/heap memory, and keep-alive are not measured;
these are not isolated standards-overhead measurements.

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

- PHP 8.4.27 or later in the 8.4 series, or PHP 8.5.12 and later PHP 8.x releases.
  PHP 8.5.0-8.5.11 and PHP 9 are outside the package constraint.
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
        return $this->greeter->greeting($request->getAttribute('name') ?? '');
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
- Initial providers passed to `new App([...])` before module bootstrap
- Attribute-driven routing with class prefixes and method-level HTTP route
  attributes
- Supported shortcut attributes: `#[Get]`, `#[Head]`, `#[Post]`, `#[Put]`,
  `#[Delete]`, `#[Connect]`, `#[Options]`, `#[Trace]`, `#[Patch]`
- Programmatic routes via `dvictorjhg\braidphp\Router\Classes\Route`
- Path parameters and middleware context through standard PSR-7 request attributes
- Query-string parsing through `Request::getQueryParams()`
- PSR HTTP message implementations for `Request`, `Response`, `Uri`, and
  `Stream`
- PSR-15 application middleware through `App::addMiddleware()` and `App::handle()`
- PSR-17 HTTP factories through `HttpFactory`, including uploaded files
- PSR-3 listener logging through an injected `LoggerInterface`, with `ErrorLogLogger` as the default
- A minimal TCP listener through `App::listen()`

## PSR-15 Middleware

Register `Psr\Http\Server\MiddlewareInterface` instances on the application.
They run in registration order before routing; responses unwind in reverse order.
A middleware can return a response without invoking the next handler.

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Router;

$app = new App([Router::class]);
$app->addMiddleware(new class implements MiddlewareInterface {
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Frame-Options', 'DENY');
    }
});

$response = $app->handle(new Request(uri: '/missing', body: ''));
echo $response->getStatusCode() . ' ' . $response->getHeaderLine('X-Frame-Options');
```

```text
404 DENY
```

`App` implements `RequestHandlerInterface`; `handle()` accepts any
`ServerRequestInterface` and returns a `ResponseInterface`. Controller responses
enter the response middleware unchanged; middleware may modify or replace them.
The bundled `Request` now implements `ServerRequestInterface`, including immutable
attributes, cookies, query parameters, uploaded-file trees, and parsed body state.
Server and cookie parameters start from `$_SERVER` and `$_COOKIE`; form POSTs
expose `$_POST`. The TCP listener does not populate PHP's form/upload globals;
use body-parsing middleware and `withParsedBody()` / `withUploadedFiles()` there.

Controllers may type-hint `ServerRequestInterface`. Matched path parameters use
the same immutable request attributes as middleware context: read them with
`getAttribute('name')` or inspect `getAttributes()`.

There is no middleware option on route declarations. Application middleware can
restrict its work by request path, but runs before route matching: captured
parameters belong to the controller's request copy, not the middleware's original
request. The native listener does not parse Cookie headers, JSON, form bodies,
or uploaded files into PSR-7 state. Supply parsing middleware using
`withCookieParams()`, `withParsedBody()`, and `withUploadedFiles()` as needed;
`Request` does not normalize `$_FILES` automatically under a SAPI either.

Message `with*` operations leave the original message unchanged, but body streams
are mutable and shared by message clones. Use `withBody()` with a separate stream
when content must be isolated.

`handle(ServerRequestInterface): ResponseInterface` is the application request
entry point. `listen()` runs that same pipeline and emits any PSR-7 response
through `Router\Http\ResponseEmitter`, which owns native HTTP serialization,
default wire headers, checked writes, and write deadlines. Response objects own
message state, not socket I/O. For custom stacks, `Core\MiddlewarePipeline`
accepts middleware instances and a terminal `RequestHandlerInterface`.

Register exception-to-response middleware first so it wraps all later middleware
and routing, as recommended by PSR-15. Without it, direct `handle()`
calls propagate exceptions; `listen()` returns a sanitized
500 response and server-side logging. Middleware instances are reused across
requests, so keep request-specific state in request attributes, not instance fields.

HTTP messages validate numeric protocol versions; responses reject status codes
outside 100-599 and invalid control characters in reason phrases, on construction and
mutation. URI normalization preserves valid percent escapes: encoded slashes
remain within one path segment and encoded query delimiters remain within their
values. Captured path parameters are decoded once with literal `+` preserved.
URI ports must be 0-65535 or null; standard HTTP/HTTPS ports are omitted from
`getPort()` and serialization. Streams derive capabilities from their native mode,
including append, exclusive-create, binary, and read/write variants.

## PHP-FIG Standards

Status checked against the [PHP-FIG accepted PSR index](https://www.php-fig.org/psr/)
on 2026-10-24. This table documents shipped interfaces and integration boundaries,
not a roadmap or a formal conformance certification.

| Standard | Current Status And Suitable Scope |
| --- | --- |
| PSR-1, PSR-4, PSR-12 | Coding conventions, Composer PSR-4 autoloading, and a PSR-12 style gate are already in use. |
| [PSR-7](https://www.php-fig.org/psr/psr-7/) HTTP messages | Bundled message, server-request, response, URI, stream, and uploaded-file implementations. Multipart parsing is not bundled. |
| [PSR-11](https://www.php-fig.org/psr/psr-11/) containers | PHPInjector's provider container already implements `ContainerInterface`. `App` still accepts PHPInjector's concrete provider container, not arbitrary PSR-11 containers; broader container interoperability would be a separate integration. |
| [PSR-15](https://www.php-fig.org/psr/psr-15/) HTTP handlers | Implemented through `App`, `MiddlewarePipeline`, and `addMiddleware()`. |
| [PSR-17](https://www.php-fig.org/psr/psr-17/) HTTP factories | `HttpFactory` implements all six factory interfaces from `psr/http-factory`: requests, responses, server requests, streams, uploaded files, and URIs. |
| [PSR-3](https://www.php-fig.org/psr/psr-3/) logging | `App(logger: $logger)` accepts `LoggerInterface`. `ErrorLogLogger` preserves server-side logging by default; a failing custom logger falls back to `error_log()`. |

PSR-5, PSR-19, PSR-21, and PSR-22 are drafts, not accepted standards. PSR-0
and PSR-2 are deprecated; PSR-8, PSR-9, and PSR-10 are abandoned. Recheck the
official index before planning future work. No PSR-6/PSR-16 cache service,
PSR-14 dispatcher, PSR-20 clock, PSR-18 HTTP client, or PSR-13 link service is
bundled. The router's internal path-segment cache is not a general cache API.

## HTTP Factories And Logging

Use `dvictorjhg\braidphp\Router\Http\HttpFactory` for PSR-17 construction.
Factory requests use exactly the supplied method, URI, and server parameters;
they do not import headers, cookies, input, or form data from PHP globals.
Normal `new Request()` calls retain their environment-based defaults. Explicit
`serverParams` disables global server, cookie, header, and form inference, but a
null constructor body still opens `php://input`; pass `body: ''` to avoid that.
HTTP message methods accept valid extension tokens such as `PROPFIND`; the router
still dispatches only methods represented by `HttpMethod`.

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Core\ErrorLogLogger;
use dvictorjhg\braidphp\Router\Http\HttpFactory;
use dvictorjhg\braidphp\Router\Router;

$factory = new HttpFactory();
$app = new App([Router::class], logger: new ErrorLogLogger());
$request = $factory->createServerRequest('GET', '/missing', ['REMOTE_ADDR' => '127.0.0.1']);
$response = $factory->createResponse(201)
  ->withHeader('Content-Type', 'text/plain')
  ->withBody($factory->createStream('created'));

echo $response->getStatusCode() . ' ' . $response->getBody() . PHP_EOL;
echo $app->handle($request)->getStatusCode();
```

```text
201 created
404
```

`HttpFactory` also exposes `createRequest()`, `createUri()`,
`createStreamFromFile()`, `createStreamFromResource()`, and
`createUploadedFile()`. Resource-based streams must be readable. Uploaded files
validate size and upload-error metadata; `moveTo()` accepts local paths, copies
the full stream with checked writes, uses `move_uploaded_file()` for PHP-managed
uploads, and prevents a second move or stream access after a successful move.
Client-provided filenames/media types remain untrusted metadata, not destination paths.

Supply any PSR-3 logger with `new App(logger: $logger)`. Listener failures are
logged at `error` level with the throwable in `context['exception']`. Malformed
requests and uncaught application errors produce generic 400/500 responses when
writing succeeds. Response-write failures are logged and the connection closes;
a replacement response is not sent. `ErrorLogLogger` accepts all eight
standard levels and logs message text plus throwable details without placeholder
interpolation. Direct `handle()` calls still propagate uncaught exceptions.

## Runtime Caveats

- `App::listen()` is a **blocking single-process** server loop.
- The example front controller reads `SERVER_ADDRESS` and `SERVER_PORT` from the
  environment and defaults to `0.0.0.0:8000`.
- The native listener has no worker manager or event loop. `App::handle()` is
  independent of it; another server needs request/response adaptation. The SAPI
  front controller in [benchmarks/braidphp.php](benchmarks/braidphp.php) demonstrates this for benchmarks,
  not as a supported production adapter.
- Scale by running multiple processes behind a process manager, reverse proxy,
  or container platform.
- The active PHPInjector is process-global, not request scoped. Do not cache
  request-specific state in shared providers or bootstrap independent apps in
  one process and expect separate containers.
- Direct `handle()` calls propagate controller and middleware
  exceptions unless exception-handling middleware catches them. `listen()` catches
  uncaught failures, logs details server-side, and returns a generic 500 response. Malformed
  requests receive a generic 400 response.
- By default, requests have a five-second total read deadline, a 64 KiB header limit, and
  an 8 MiB body limit. Invalid or duplicate `Content-Length`, truncated bodies,
  and `Transfer-Encoding` are rejected. Chunked requests are not supported.
- Response writes retry partial writes and have a separate five-second default deadline.
  Failed writes are logged and the client is closed without stopping the listener.
- Native response emission buffers the body and complete HTTP payload before
  writing. The write deadline starts after serialization; it does not limit
  controller execution or body conversion. This is not a streaming-response runtime.
- Arrays are not converted to JSON automatically. Return a `Response` with an
  explicit JSON content type and `json_encode(..., JSON_THROW_ON_ERROR)` body.

## Configure HTTP Limits

Pass `HttpOptions` to the application; no vendor edits or global settings are
needed. `new App()` and `RequestParser::fromResource($stream)` use the defaults.
Options are immutable and invalid values throw
`InvalidArgumentException` at construction.

| Option | Default | Meaning |
| --- | --- | --- |
| `maxHeaderBytes` | `65_536` | Entire request line and headers, including CRLF separators; at least 4 and at most `PHP_INT_MAX - 2`. |
| `maxBodyBytes` | `8_388_608` | Maximum declared body length; non-negative. Use `0` to reject nonempty bodies. |
| `readTimeout` | `5.0` | Finite positive seconds for the entire header and body read, not per chunk. |
| `writeTimeout` | `5.0` | Finite positive seconds for socket writes after response buffering and serialization. |
| `backlog` | `128` | Positive pending-connection queue size passed to the socket; subject to operating-system limits. |

This complete example configures an application and uses the same options for
direct parsing without opening a listening socket:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Router\Http\HttpOptions;
use dvictorjhg\braidphp\Router\Http\RequestParser;

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
    $request = RequestParser::fromResource($stream, $options);
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
    $response = $app->handle(new Request(method: HttpMethod::GET, uri: $uri));
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

Before either container workflow, create a local `.env` at the repository root
with these development settings:

```dotenv
ENVIRONMENT=development
APP_PATH=/app
SERVER_ADDRESS=0.0.0.0
SERVER_PORT=8000
DOCKER_IMAGE_TAG=php-8.5.12-cli-trixie
```

Compose passes that file into the container. The Podman helpers read selected
settings and explicitly set the runtime environment and server address/port;
they do not forward `XDEBUG_MODE` from `.env`. These settings are for local
development; use the production image target and the security guidance for
deployment.

Typical local Podman flow:

```bash
./bin/podman-run.sh --environment development --detach
podman exec braidphp-development composer test
./bin/podman-run.sh --action down
```

The launchers require a local `.env` file. Set `SERVER_PORT=8000` and
`DOCKER_IMAGE_TAG=php-8.5.12-cli-trixie` to label the image consistently with the
Dockerfile's PHP base; this tag does not select the base version. The Podman
launcher appends the environment name, producing
`localhost/dvictorjhg/braidphp:php-8.5.12-cli-trixie-development`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md),
and [AI_USE_POLICY.md](AI_USE_POLICY.md).

## Security

See [SECURITY.md](SECURITY.md). Report vulnerabilities privately instead of
opening a public issue.

## Release / Publishing

For each release, finalize the dated entry in [CHANGELOG.md](CHANGELOG.md),
align [CITATION.cff](CITATION.cff) and the English and Spanish documentation
version labels, and run every development command above. Review `composer audit`
and inspect a `composer archive` build for source, licensing files, and unwanted
local artifacts before creating the annotated version tag.
No tag, push, or publication is part of local release preparation.

Publish any required PHPInjector release first. Verify installation, the intended
dependency constraint, and a minimal application in a clean consumer project
before publishing. After the BraidPHP tag is on GitHub, publish a GitHub Release
and refresh Packagist. Composer derives the package version from Git tags; do not
add a `version` field to `composer.json`. Release-specific behavior and
compatibility requirements belong in the changelog.

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
