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

## Documentation

- **Full guide:** [GitHub Pages documentation](https://dvictorjhg.github.io/braidphp/)
- **Local static entry point:** [`docs/index.html`](docs/index.html)
- **First application walkthrough:** [`docs/index.html#first-app`](docs/index.html#first-app)
- **Modules and dependency injection:** [`docs/index.html#modules`](docs/index.html#modules)
- **Routing reference:** [`docs/index.html#routing`](docs/index.html#routing)
- **HTTP messages and runtime:** [`docs/index.html#http`](docs/index.html#http), [`docs/index.html#runtime`](docs/index.html#runtime), [`docs/index.html#operations`](docs/index.html#operations)
- **Development and release workflow:** [`docs/index.html#development`](docs/index.html#development)

## Requirements

- PHP 8.4+
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
`index.php`, see [`docs/index.html#first-app`](docs/index.html#first-app).

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
- `composer coverage:check` enforces a minimum **80%** statement coverage from
  the generated Clover XML.
- [`codecov.yml`](codecov.yml) separately configures Codecov project and patch
  targets at **50%**.

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

The default `.env` values expose `SERVER_PORT=8000` and build the PHP image tag
`php-8.5.9-cli-trixie`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md),
and [AI_USE_POLICY.md](AI_USE_POLICY.md).

## Security

See [SECURITY.md](SECURITY.md). Report vulnerabilities privately instead of
opening a public issue.

## Release / Publishing

For a release, update [CHANGELOG.md](CHANGELOG.md), run the validation commands
above, create and push an annotated tag such as `1.0.2`, publish a GitHub
Release, and refresh the package on Packagist.

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
