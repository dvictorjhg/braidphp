# BraidPHP HTTP Comparison

## Fresh Toolchain And Setup

All figures below were collected freshly on **2026-10-19**, after deleting the
previous generated benchmark results. Historical tables and before/after claims
from those removed datasets are not retained as current evidence.

| Component | Recorded version | Freshness check |
| --- | --- | --- |
| k6 | 2.3.0, commit e088784614 | Official GitHub Latest stable release, published 2026-09-21 |
| PHP / OPcache | 8.5.11 | Official PHP stable release listing |
| Composer | 2.10.3 | Official Composer stable version listing |
| Slim | 4.15.3 | Composer current stable package metadata |
| slim/psr7 | 1.8.0 | Composer current stable package metadata |
| Flight | 3.19.4 | Composer current stable package metadata |
| php-injector | 1.0.2 | Composer current stable package metadata |

The k6 Linux amd64 archive was checked against both its GitHub release-asset
SHA-256 digest and the official checksum manifest. Archive SHA-256:
`39c3117b6af817592dcd0ce4242105c0a7af10948c2a425306f0be8f7a8a8ab1`.
The extracted binary SHA-256 is recorded in each run's metadata:
`e001bbf39ba67424a85c9c8516440ba5c73ecf7a6021be96c76194380c15f260`.
Use the [official Latest release](https://github.com/grafana/k6/releases/latest),
not the latest-dated old-major maintenance release, when refreshing k6.

`composer update --no-dev --optimize-autoloader` found nothing to upgrade in the
isolated benchmark installation. `composer outdated --all --strict` confirmed
all 14 direct/transitive packages current; `composer audit --locked` found no
security advisories. This audit covers benchmark dependencies, not unrelated
development-tool migrations in the root or sibling project. The checkout path
dependency is intentionally not replaced by a published BraidPHP release.

- Host: Linux 7.2.11, AMD RYZEN AI MAX+ 392, 12 physical cores / 24 threads.
- Server pinned to CPU 2; k6 pinned to CPUs 4-7, on separate physical cores.
- BraidPHP: modified local checkout based on `f360666a84a266e3e85a1bf228368783722fdc69`;
  source hashes and dirty status are retained in metadata.
- All frameworks share one optimized no-dev Composer autoloader and no optional
  route cache. CLI OPcache is on; JIT, timestamp validation, and Xdebug mode are off.
- Three-route run: 10:36:58-10:49:08 UTC, 1 and 16 users, 120 measured samples.
- 100-route run: 10:49:24-10:55:30 UTC, 16 users, 60 measured samples. Register
  97 unused parameter routes before the three measured handlers.
- Three five-second repeats per cell, with one second of warmup before each.
  Rotate target order between rounds; workload/concurrency order stays fixed.
- Loopback HTTP, no connection reuse or think time. Validate status, exact body,
  and media type on every request; expected 404s are successes.

**180 samples; 10,136,660 measured requests; zero request errors or failed checks.**

## Shared Server, 16 Users

Median requests/second and median per-run JSON p95 HTTP duration. Applications
bootstrap per request behind PHP's single-process built-in server, so these
measurements include route registration, not only dispatch. The plain-PHP control
has no framework or route table and is not a 100-route peer.

| Handler routes | Application | Plaintext req/s (higher is better) | JSON req/s (higher is better) | Parameter req/s (higher is better) | 404 req/s (higher is better) | JSON p95 ms (lower is better) |
| ---: | --- | ---: | ---: | ---: | ---: | ---: |
| 3 | Plain PHP control | 25,346 | 24,127 | 24,423 | 26,539 | 1.065 |
| 3 | BraidPHP | 12,054 | 12,137 | 10,274 | 11,386 | 1.938 |
| 3 | Slim | 6,903 | 6,511 | 6,225 | 7,399 | 3.602 |
| 3 | Flight | 15,108 | 12,804 | 10,714 | 12,261 | 2.116 |
| 100 | Plain PHP control, no route table | 22,925 | 21,869 | 24,653 | 24,019 | 1.576 |
| 100 | BraidPHP | 7,203 | 7,801 | 6,768 | 6,956 | 3.072 |
| 100 | Slim | 2,668 | 2,764 | 2,745 | 2,956 | 7.878 |
| 100 | Flight | 2,939 | 2,753 | 2,821 | 1,942 | 8.140 |

Flight leads throughput in these three-route workloads; BraidPHP leads these
100-route workloads. That describes this uncached per-request application setup,
not universal framework rankings. Slim's production route cache or different
application lifecycle/registration strategies would change the comparison.

## Native Runtime, Separate Model

BraidPHP bootstraps once per process using `App::listen()`. Its HTTP parser/emitter
and connection logging differ from the shared-server setup. This lifecycle
advantage is not a routing-only speedup or a production deployment recommendation.

| Handler routes | VUs | Plaintext req/s (higher is better) | JSON req/s (higher is better) | Parameter req/s (higher is better) | 404 req/s (higher is better) | JSON p95 ms (lower is better) |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 3 | 16 | 25,515 | 22,340 | 25,766 | 22,669 | 1.207 |
| 100 | 16 | 20,197 | 20,246 | 19,829 | 24,980 | 1.394 |

## CPU And Memory, JSON At 16 Users

Only the PHP server process is measured using Linux `/proc`, not k6, the runner,
or the entire host. CPU 100% means one core. CPU us/request divides server CPU
time by completed measured requests. CPU %, CPU us/request, and mean RSS are
medians of per-run values; sampled peak RSS is the maximum across repeats.

Lower resource cost is better for equivalent work and process history. CPU %
alone is not a ranking: low utilization can also mean fewer requests are served.

| Handler routes | Application | CPU % (context-dependent) | CPU us/request (lower is better) | Mean RSS MiB (lower is better) | Max sampled RSS MiB (lower is better) |
| ---: | --- | ---: | ---: | ---: | ---: |
| 3 | Plain PHP control | 51.2 | 18.7 | 56.3 | 60.3 |
| 3 | BraidPHP, shared server | 87.3 | 72.2 | 47.5 | 49.6 |
| 3 | Slim, shared server | 89.8 | 140.1 | 45.8 | 46.5 |
| 3 | Flight, shared server | 84.2 | 66.3 | 49.2 | 52.4 |
| 100 | Plain PHP control, no route table | 44.7 | 19.1 | 45.9 | 49.8 |
| 100 | BraidPHP, shared server | 92.5 | 119.4 | 40.1 | 41.1 |
| 100 | Slim, shared server | 95.2 | 344.9 | 39.0 | 39.5 |
| 100 | Flight, shared server | 95.9 | 350.3 | 38.6 | 39.0 |
| 3 | BraidPHP, native runtime | 42.6 | 21.2 | 36.7 | 36.8 |
| 100 | BraidPHP, native runtime | 64.8 | 34.6 | 36.9 | 36.9 |

These compare different achieved request rates, not resources at an identical
offered throughput. CPU per request helps distinguish saturation from work per
completed request, but is still specific to these small handlers and deployments.

RSS includes PHP, extensions, and resident shared pages; it is neither private
memory nor framework allocations. Each round reuses a server across workloads,
so retained state and preceding request counts influence later RSS values. The
three-route dataset runs one-user workloads before 16 users; the 100-route
dataset does not. Do not interpret lower 100-route RSS as a route-table saving,
or larger baseline RSS as a framework memory advantage. No heap profile or
memory-leak diagnosis is implied.

RSS is sampled approximately every 100ms and can miss brief peaks; it is not a
kernel lifetime high-water mark. CPU counters span measured k6 launch through
exit, including launch/exit idle intervals but excluding server startup/warmup.
Raw samples retain PID start ticks, user/system CPU totals, elapsed windows, and
sample counts. [Measurement details](README.md#server-cpu-and-rss) define the units.

## One User, Three Routes

Median requests/second. All workloads have the same correctness checks.

| Application | Plaintext req/s (higher is better) | JSON req/s (higher is better) | Parameter req/s (higher is better) | 404 req/s (higher is better) |
| --- | ---: | ---: | ---: | ---: |
| Plain PHP control | 8,423 | 7,656 | 7,601 | 7,819 |
| BraidPHP, shared server | 5,596 | 4,309 | 4,528 | 5,536 |
| Slim | 3,933 | 3,719 | 4,243 | 5,006 |
| Flight | 4,981 | 4,605 | 5,716 | 6,572 |
| BraidPHP, native runtime | 7,451 | 7,756 | 7,877 | 6,483 |

## Warm Router Snapshot

The router reuses immutable route-path segments in a weak map, not matched
parameters or custom-matcher results. Removed routes are not retained by its
keys. Matching stays linear and declaration-ordered. The cache starts cold on
each SAPI request; native mode can reuse it across requests.

Fresh [router.php](router.php) measurement: CPU 2, PHP 8.5.11, OPcache on, JIT and
Xdebug off, five repeats of 2000 dispatches after warmup. The parameter case
matches the last-declared route; setup and HTTP are excluded. No old before/after
percentage is carried forward from deleted evidence.

| Routes | Parameter us/dispatch (lower is better) | Missing us/dispatch (lower is better) |
| ---: | ---: | ---: |
| 3 | 0.939 | 0.508 |
| 100 | 15.224 | 10.638 |
| 1000 | 144.590 | 101.646 |

## Evidence And Limits

- [Three-route results](results/20261019-k6-v2-routes-3/RESULTS.md): 120 samples,
  6,724,444 requests, including one-user results, ranges, p95/p99, CPU, and RSS.
- [100-route results](results/20261019-k6-v2-routes-100/RESULTS.md): 60 samples,
  3,412,216 requests, including ranges, p95/p99, CPU, and RSS.
- Each directory contains metadata, source hashes, raw k6 summaries, samples,
  and gzip-compressed subprocess logs. [Router samples](results/router-current.json)
  include current source hashes.
- [Release metadata](results/braidphp-k6-latest-release.json),
  [checksum manifest](results/k6-v2.3.0-checksums.txt),
  [dependency freshness](results/dependency-freshness.json), and
  [security audit](results/dependency-audit.json) preserve the tooling checks.
- Generated evidence is local and Git-ignored. Archive `results/` alongside this
  report when sharing or publishing measurements; links are local until then.
- BraidPHP shared-server JSON throughput ranges from 7,979 to 12,223 req/s with
  three routes and 6,499 to 8,455 req/s with 100 routes. Ranges are not confidence
  intervals. Percentiles are medians of per-run percentiles, not pooled ones, and
  exclude connection setup; throughput includes opening connections.
- Five-second local samples and closed-loop load cannot establish production
  capacity or significance. No DB, injected service graph, private/heap memory,
  production FPM/workers, keep-alive, mixed traffic, slow-client resistance, or
  fixed-arrival-rate overload is measured. No causal speedup is inferred from
  comparing old-k6 and new-k6 runs.

See [README.md](README.md) for exact commands and workload definitions.
