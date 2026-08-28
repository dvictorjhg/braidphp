# Contributing

Thank you for contributing to BraidPHP.

BraidPHP is an attribute-driven PHP framework with module composition, routing,
PSR HTTP messages, and a lightweight single-process TCP runtime. Changes should
preserve those boundaries and remain understandable to applications using the
package directly.

## Before You Start

- Open an issue first for breaking changes, new public APIs, or major behavioral changes.
- Keep pull requests focused. Smaller changes are easier to review and safer to release.
- Make sure your change fits the framework scope: modules, routing, HTTP messages, runtime behavior, or closely related tooling.

## Local Workflow

Install dependencies and run the static analysis:

```bash
composer install
composer analyse
```

Run the unit and integration test suite before opening a pull request:

```bash
composer test
```

If you switch branches, move files, or see repeated `Class ... not found` errors,
refresh Composer autoload files and rerun the suite:

```bash
composer dump-autoload
composer test
```

## Development Expectations

- Match the existing coding style and keep `declare(strict_types=1);` where the project already uses it.
- Add or update focused PHPUnit tests for behavioral changes.
- Update `README.md`, `CHANGELOG.md`, and the documentation source when public behavior or examples change.
- Keep documentation assets relative so the static page works from GitHub Pages and from a local checkout.
- Keep the implementation compatible with PHP `^8.4`.
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/).
- Avoid unrelated refactors in the same pull request.

## Pull Requests

Use the pull request template and complete the relevant verification steps:

```text
composer validate --strict
composer check-platform-reqs
composer analyse
composer check-style
composer test
composer test:coverage
composer coverage:check
```

The CI workflow runs these checks on PHP 8.4 and 8.5. GitHub issue forms and a
pull request template are included to keep reports and reviews consistent.

## Reporting Bugs

When opening an issue, include:

- PHP version
- BraidPHP version or commit
- Operating system and installation method
- Minimal reproducible example
- Expected behavior
- Actual behavior

Report security vulnerabilities privately according to [SECURITY.md](SECURITY.md)
instead of opening a public issue. Generated contributions should retain
provenance and attribution as described in [AI_USE_POLICY.md](AI_USE_POLICY.md).

## License

By submitting a contribution, you agree that your work may be distributed under
Apache-2.0 with the repository copyright and attribution notices preserved.
