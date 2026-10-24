# HTTP Benchmarks

Compare [BraidPHP](https://github.com/dvictorjhg/braidphp) with
[Slim 4](https://www.slimframework.com/) and [Flight 3](https://docs.flightphp.com/),
two small HTTP frameworks. Laravel and Symfony full-stack applications are not
scope-equivalent peers. Plain PHP provides a server/runtime baseline.

The published report is a dated source snapshot, not a continuously current
performance claim. Its provenance and later source changes are identified in
[REPORT.md](REPORT.md); rerun after source or dependency changes.

The recorded 1.2.0 matrix and router snapshot were refreshed after the
API/ownership cleanup. The report links the four complete datasets and records
54 source and executable harness hashes. Short smoke runs verify correctness
only; rerun the full matrix after changing measured source or dependencies.

## Requirements And Run

Linux with readable `/proc`, PHP 8.4.27+ or 8.5.12+ with CLI OPcache, Composer, and
[k6](https://grafana.com/docs/k6/latest/set-up/install-k6/).
Benchmark dependencies are isolated and their lock file is tracked; they never
enter BraidPHP's runtime dependency graph. A Composer path repository links the
current checkout into this isolated dependency tree. All three frameworks use
the same optimized, no-dev autoloader, without changing the root installation.

From the project root, in fish:

```fish
composer --working-dir=benchmarks install --no-dev --prefer-dist --optimize-autoloader
k6 version
composer --working-dir=benchmarks outdated --all --strict
composer --working-dir=benchmarks audit --locked
php benchmarks/run.php --self-test
php benchmarks/features.php --self-test
env XDEBUG_MODE=off php benchmarks/run.php
```

Before a new comparison, verify the official [latest stable k6 release](https://github.com/grafana/k6/releases/latest),
[PHP releases](https://www.php.net/releases/), and [Composer releases](https://getcomposer.org/download/).
GitHub's **Latest** marker identifies the current k6 major; a later-dated release
can be a maintenance backport for an older major. Verify the release archive's
SHA-256 against the official checksum manifest before extracting it. Install k6
on `PATH`, or set `K6_BIN` to its absolute path. Do not assume an old local binary
or a floating container tag is current.

`install` reproduces the tracked dependency lock. For a refreshed comparison,
run `composer --working-dir=benchmarks update --no-dev --optimize-autoloader`,
review the lock changes, and rerun the freshness/security checks above. Review
major-version constraints if `outdated --all` reports a newer incompatible stable
release; do not silently label an older major current. Recorded versions and
release-verification details are in [REPORT.md](REPORT.md).

The default is three 10-second samples per target/workload/concurrency, preceded
by one second of warmup each. Expect about 22 minutes. The runner fails on any
incorrect response, request error, occupied port, or failed subprocess. Servers
are loopback-only and shut down after each target. Do not run it on a busy host.

For the shorter configuration used in the recorded report:

```fish
env XDEBUG_MODE=off DURATION=5s REPEATS=3 VUS=16 SERVER_CPU=2 LOAD_CPUS=4-7 php benchmarks/run.php
```

`SERVER_CPU` and `LOAD_CPUS` are optional Linux `taskset`
affinities. Select separate physical cores using `lscpu --extended`; sibling
threads are not separate cores. `BENCH_PORT` defaults to 8091. `OUTPUT_DIR` must
be a new directory; otherwise a timestamped directory under `results/` is used.

`ROUTE_COUNT` defaults to 3 and accepts 3-10000 handler routes. To reproduce the
larger route table, add `ROUTE_COUNT=100 VUS=16` to the environment. For example:

```fish
env XDEBUG_MODE=off DURATION=5s REPEATS=3 VUS=16 ROUTE_COUNT=100 SERVER_CPU=2 LOAD_CPUS=4-7 php benchmarks/run.php
```

`BENCH_PROFILE` defaults to `bare`. Repeat both route-count configurations with
`BENCH_PROFILE=features` for the matched middleware/service workload. The release
matrix uses 16 VUs, three five-second repeats, and both profiles at 3 and 100
routes: 240 measured samples, approximately 25 minutes in total.

## What Is Compared

| Target | Lifecycle | HTTP server |
| --- | --- | --- |
| baseline | Plain PHP per request, no Composer/framework | PHP built-in, one process |
| braidphp-sapi | Attribute module/controller bootstrap per request | PHP built-in, one process |
| slim | Route registration and PSR-7 dispatch per request | PHP built-in, one process |
| flight | Route registration and dispatch per request | PHP built-in, one process |
| braidphp-native | Module/controller bootstrap once per process | BraidPHP `App::listen()`, one process |

The common-server rows compare complete minimal applications under the same
SAPI, not isolated router functions. BraidPHP's native row is a separate
deployment-model comparison, **not evidence of inherently faster routing**.
It avoids rebuilding application state on each request and uses a different
HTTP parser/emitter. PHP's built-in server also logs connections while BraidPHP's
native server does not. Neither setup is a production deployment recommendation.

Every framework has the following three measured handlers in the same order:

| Workload | Request | Expected response |
| --- | --- | --- |
| plaintext | `GET /plaintext` | 200, `text/plain`, `Hello, World!` |
| json | `GET /json` | 200, `application/json`, `{"message":"Hello, World!"}` |
| parameter | `GET /users/42` | 200, `application/json`, `{"id":"42"}` |
| missing | `GET /missing` | 404, `text/plain`, `Not Found` |

For `ROUTE_COUNT=100`, register 97 unused parameterized routes before these
handlers (`/unused/1/:id`, etc., using each framework's native syntax). This
exercises uncached per-request route registration and late-table lookup as well
as response handling. BraidPHP still uses its attribute controller for the
measured handlers. Its structural root prefix is not counted as a handler.
The plain-PHP control has no framework route table and is not a 100-route peer.

JSON is encoded per request, not pre-encoded. The 404 response is deliberately
normalized across frameworks, with Slim's normal exception/error-middleware
dispatch retained. Flight receives a stable front-controller `SCRIPT_NAME` so
PHP's development router does not mistake nested paths for a base directory.
The `bare` profile has no DB, templates, authentication, external I/O, additional
middleware, enabled route caches, or injected service graph. BraidPHP still uses
its normal controller resolution path.

The `features` profile keeps those same routes, bodies, and status codes. Every
target loads Composer and resolves the same registered two-class PHPInjector
service graph, adds request context, and returns `X-Benchmark-Feature:
service:enabled`. BraidPHP and Slim run the same two PSR-15 middleware objects;
Slim's reversed registration order preserves the same execution order. Flight
uses its native pre-dispatch hook and response-emission filter; plain PHP uses
equivalent direct operations. Flight and PHP are not presented as PSR-15 engines.
The baseline therefore includes Composer/PHPInjector only in this profile.
Native BraidPHP retains its once-per-process bootstrap and provider reuse, so it
remains a separate lifecycle comparison.

Full feature coverage is split deliberately: HTTP tests cover modules,
attribute controllers, routing, service resolution, middleware, request
attributes, responses, and native parsing/emission. The PHPUnit contract suite
covers all six factories, streams, uploads and file movement, server-request
state, logger injection/fallback, and failure paths. Run `composer test:coverage`
and `composer coverage:check` from the project root before a release. Disk I/O
and failing-request logging are not silently added to only one framework's
throughput workload; these results do not measure their performance.

## Measurement

The runners load one-class fixture files from `fixtures/`; shared response
payloads and media types live in `payloads.php`. Metadata fingerprints every
runtime PHP source and all ten executable harness/fixture files, including
`features.php`, so source changes remain distinguishable from recorded results.

- k6 constant-VU closed-loop load, 16 VUs in the current report, with no think time.
- One server at a time; target order rotates between rounds. This reduces, but
  does not eliminate, order/temperature bias. Concurrency/workload order is fixed.
- Plain HTTP over loopback, one new connection per request for every target.
  BraidPHP's native server closes connections, so keep-alive cannot be compared
  equivalently. No TLS, proxy, gzip, or network traversal is measured.
- Xdebug mode is off, CLI OPcache is on, timestamp validation and JIT are off.
  The runtime settings, CPU/OS, PHP/k6/Composer versions, k6 binary SHA-256,
  dependency versions, profile, source and harness hashes, working-tree status, and target order are
  retained in `metadata.json`.
- All frameworks load through one optimized Composer autoloader, with BraidPHP
  linked from the checkout rather than fetched from a release. The baseline
  does not load Composer in the bare profile. Root development autoloaders are not used.
- Status, exact response body, media type, and profile-specific feature header are checked on every request.
  Expected 404s are successes; every other unexpected status is a failure.
- Results show median requests/second and median per-run p95/p99 HTTP duration.
  Percentiles are **not** pooled percentiles. k6 HTTP duration excludes connection
  setup; throughput includes the cost of opening new connections.
- Min/max throughput across repeats is a variability range, not a confidence
  interval. Five-second samples are exploratory, not publication-grade evidence.

### Reading Results

- **Higher is better:** throughput in requests/second, including expected 404 responses.
- **Lower is better:** p95/p99 latency, dispatch time, CPU us/request, sampled RSS,
  and request/check failures. Compare resource costs for equivalent work and
  process history; RSS is not framework-only memory.
- **CPU % is context-dependent:** lower is better only at equivalent throughput.
  Low utilization can also mean less work is being completed.
- Route counts, VUs, repeats, and request totals describe the test, not a score.

### Server CPU And RSS

The runner reads Linux `/proc/<server-pid>/stat` and `/status`, not whole-host,
k6, or runner resource usage. Warmup and server startup are excluded. Each resource
window begins immediately before the measured k6 process launches and ends when
it exits, so k6 launch/exit idle intervals are included in its duration.

- CPU seconds are the delta of user plus system ticks divided by `getconf CLK_TCK`.
- CPU % is CPU seconds divided by resource-window seconds times 100. One fully
  occupied core is 100%, regardless of the host's logical CPU count.
- CPU us/request is server CPU seconds times one million divided by measured
  completed requests. It is not a latency percentile or load-generator CPU cost.
- RSS is sampled approximately every 100ms; raw samples store the sample count,
  start/end counters, elapsed window, mean RSS, and maximum sampled RSS. Short
  peaks may be missed; this is not the kernel's lifetime high-water mark.
- RSS includes PHP, extensions, and resident shared pages, not only framework
  allocations. It is not private memory, PHP heap size, or an allocation profile.
- One server is reused across all workloads/concurrency levels within a round.
  RSS can retain state from earlier requests. Route-count datasets use the same
  workload order but have different preceding request counts and process history;
  their RSS values do not isolate route-table cost.
- Reports aggregate median CPU %, median per-run CPU us/request, median per-run
  mean RSS, and maximum sampled RSS across repeats. Request totals exclude warmup.

## Outputs And Limits

Each `results/<timestamp>/` contains `RESULTS.md`, `metadata.json`, `samples.json`,
per-run k6 summaries, warmup summaries, and subprocess logs. Generated results and
peer dependencies are ignored by Git. The recorded interpretation lives in
[REPORT.md](REPORT.md).

These tests measure tiny-route throughput, latency, server CPU, and sampled RSS,
not framework quality, private/heap memory, scalability across cores, slow-client resistance, mixed traffic,
isolated dependency-injection cost, or realistic application throughput. A closed-loop
test backs off with the server and can hide overload; use a separately designed
constant-arrival-rate test for an SLO/capacity claim. Production comparisons need
matched FPM/worker deployments, longer samples, and an isolated load-generator
machine. Do not extrapolate the native row into a general performance ranking.

## Focused Router Benchmark

To isolate route dispatch from network and application bootstrap costs:

```fish
env XDEBUG_MODE=off taskset -c 2 php -d opcache.enable_cli=1 -d opcache.jit=disable benchmarks/router.php
```

This tests a last-declared parameter route and an unmatched path in tables of 3,
100, and 1000 routes. Defaults are five repeats of 2000 dispatches after warmup.
`ITERATIONS` and `REPEATS` override those counts; `SUMMARY` saves JSON samples.
The request and routes are constructed before timing. This is a warm router
microbenchmark, **not HTTP throughput**, and does not measure cold route setup.
It reports median microseconds per dispatch and checks the resulting match.
