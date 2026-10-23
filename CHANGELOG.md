# Changelog

All notable changes to `BraidPHP` will be documented in this file.

Updates should follow the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
principles and Semantic Versioning.

## [Unreleased]

## [1.1.1] - 2026-10-23

### Security

- Require PHP 8.4.27+ or 8.5.12+ and update development and production container bases to PHP 8.5.12.
- Reject invalid response status codes, reason-phrase control bytes, and malformed protocol versions during construction and mutation to prevent HTTP response splitting.
- Audit Composer dependencies in CI and document reverse-proxy, development-image, and shared-provider trust boundaries.

### Changed

- Expand request parsing, stream failure, SAPI header, and listener regression coverage, reaching 99.76% statement coverage without excluding defensive code.
- Replace stale version-specific publishing instructions with a reusable release checklist.

## [1.1.0] - 2026-10-19

### Added

- Reproducible k6 comparisons against Slim and Flight with 3- and 100-route workloads, plus a README "Why to use?" feature and performance comparison.
- A focused router dispatch benchmark and regression coverage for repeated parameters and route replacement.
- Server-process CPU time, CPU per request, and sampled mean/peak RSS in HTTP benchmarks, with raw resource counters and documented limits.
- Immutable HttpOptions for per-application header/body limits, total read/write deadlines, and listen backlog, also accepted by direct request parsing.
- Executable HTTP configuration examples and documented tool configuration overrides.
- Locally bundled Highlight.js 11.12.0 syntax highlighting with light/dark colors and an explicitly registered HTTP grammar.

### Changed

- Reuse parsed immutable route-path segments through a per-router weak cache, preserving route order, custom matchers, and request-specific parameters.
- Refresh benchmark evidence using checksum-verified k6 2.3.0 and audited current stable dependencies; record the load-generator binary hash and Composer version.
- Default socket request reads and response writes to five seconds, request headers to 64 KiB, and bodies to 8 MiB; all are configurable through HttpOptions.
- Allow coverage report paths and minimum percentages to be passed through Composer, retaining an 80% default gate.
- Exclude local dependencies, generated reports, environment files, and local lockfiles from Composer archives; align release metadata and the main-branch alias.
- Return generic listener error responses, log details server-side, and reject malformed request framing with 400 responses.
- Verify executable documentation in CI on PHP 8.5.
- Reworked the README and GitHub Pages documentation to match the current module, routing, HTTP, runtime, container, and development behavior.
- Added a step-by-step first-application walkthrough and clarified tested/runtime platform expectations, coverage gates, and single-process limitations.

### Fixed

- Reject invalid header names and control characters in header values during message construction and header mutation to prevent response-header injection.
- Accept origin-form and absolute-form OPTIONS targets while retaining asterisk-form support.
- Validate parsed HTTP protocol tokens and expose the version without the HTTP/ prefix as required by PSR-7.
- Exclude benchmark dependencies and generated results from Composer archives while retaining the reproducible harness and its lockfile.
- Parse exact Content-Length headers with optional whitespace, reject duplicate lengths and truncated bodies, and reject unsupported Transfer-Encoding.
- Complete partial response writes and keep write failures from terminating the listener.

## [1.0.2] - 2026-08-31

### Changed

- Simplified HTTP message, response, stream, URI, and route-scanning internals without changing the public API.
- Removed unused stream metadata from serialized payloads while preserving content and stream capability restoration.
- Refined routing and HTTP documentation with responsive layouts, project branding, and clearer guidance.

## [1.0.1] - 2026-08-29

### Changed

- Made the `Example` namespace development-only.
- Excluded the `Example/` directory from production package archives.

## [1.0.0] - 2026-08-28
