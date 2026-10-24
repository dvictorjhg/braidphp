# BraidPHP HTTP Comparison

The measurements below describe the **BraidPHP 1.2.0 candidate**. All four HTTP
datasets record the same 54 source and executable harness hashes, verified against
the measured checkout.

## Recorded Toolchain And Setup

All HTTP figures were collected on **2026-10-24, 09:54-10:20 UTC** against
the **BraidPHP 1.2.0 candidate**, including its PSR-3/15/17 features and
contract/security fixes. Four datasets cover bare and
equal-work feature profiles at 3 and 100 routes. These measurements do not
establish a before/after speedup or isolated standards overhead.

| Component | Recorded version | Freshness check |
| --- | --- | --- |
| k6 | 2.3.0, commit e088784614 | Official GitHub Latest stable release, published 2026-09-21 |
| PHP / OPcache | 8.5.12 | Official PHP stable release listing |
| Composer | 2.10.3 | Official Composer stable version listing |
| Slim | 4.15.3 | Composer current stable package metadata |
| slim/psr7 | 1.8.0 | Composer current stable package metadata |
| Flight | 3.19.4 | Composer current stable package metadata |
| php-injector | 1.0.5 | Published dependency, including the cycle guard |
| psr/http-factory / psr/log | 1.1.0 / 3.0.2 | Installed standard interfaces |

The k6 Linux amd64 archive was checked against both its GitHub release-asset
SHA-256 digest and the official checksum manifest. Archive SHA-256:
`39c3117b6af817592dcd0ce4242105c0a7af10948c2a425306f0be8f7a8a8ab1`.
The extracted binary SHA-256 is recorded in each run's metadata:
`e001bbf39ba67424a85c9c8516440ba5c73ecf7a6021be96c76194380c15f260`.
Use the [official Latest release](https://github.com/grafana/k6/releases/latest),
not the latest-dated old-major maintenance release, when refreshing k6.

The isolated lock matches BraidPHP's patched PHP floor and PHPInjector ^1.0.5
requirement. A no-dev optimized install and dry-run install succeeded.
`composer outdated --all --format=json` reports all tagged dependencies current;
`composer audit --locked` found no advisories. All 14 installed packages are
recorded. The BraidPHP path dependency intentionally points to the release
candidate, not a published release.

- Host: Linux 7.2.11, AMD RYZEN AI MAX+ 392, 12 physical cores / 24 threads.
- Server pinned to CPU 2; k6 pinned to CPUs 4-7, on separate physical cores.
- BraidPHP: modified local checkout based on `2fd30e51e198506f26e0e65e34e7976bd1e6a266`;
  54 runtime/harness hashes, a SHA-256 digest of the combined staged and unstaged
  source diff, and dirty status
  are retained in metadata. All four datasets have identical source, dependency,
  autoloader, runtime, and k6 fingerprints.
- All frameworks share one optimized no-dev Composer autoloader and no enabled
  route cache. CLI OPcache is on; JIT, timestamp validation, and Xdebug mode are off.
- Bare three-route run: 09:54:01-10:00:07 UTC, 60 measured samples.
- Bare 100-route run: 10:00:28-10:06:33 UTC, 60 measured samples.
- Feature three-route run: 10:07:20-10:13:26 UTC, 60 measured samples.
- Feature 100-route run: 10:14:51-10:20:56 UTC, 60 measured samples.
- Every dataset uses 16 users. The 100-route tables register 97 unused
  parameter routes before the three measured handlers.
- Three five-second repeats per cell, with one second of warmup before each.
  Rotate target order between rounds; workload/concurrency order stays fixed.
- Loopback HTTP, no connection reuse or think time. Validate status, exact body,
  media type, and feature header on every request; expected 404s are successes.

**240 samples; 15,488,351 measured requests; zero request errors or failed checks.**

The bare profile registers no application middleware or service graph. The
feature profile resolves the same registered two-class PHPInjector graph and
adds request context plus `X-Benchmark-Feature: service:enabled` on all targets.
BraidPHP and Slim execute the same two PSR-15 middleware objects; Flight uses
its native pre-dispatch hook and response filter. Plain PHP performs equivalent
operations and loads Composer/PHPInjector only in this profile. Native BraidPHP
reuses its process and providers, so it remains a separate lifecycle comparison.

Contract tests cover all six PSR-17 factories, uploaded-file movement, stream and
server-request state, logger injection/fallback, and failure paths. These are
release checks, not claims about upload I/O or error-logging throughput. No
feature-specific disk/logging work is added only to BraidPHP's compared workload.

## Shared Server, 16 Users

Bare-profile median requests/second and median per-run JSON p95 HTTP duration. Applications
bootstrap per request behind PHP's single-process built-in server, so these
measurements include route registration, not only dispatch. The plain-PHP control
has no framework or route table and is not a 100-route peer.

| Handler routes | Application | Plaintext req/s (higher is better) | JSON req/s (higher is better) | Parameter req/s (higher is better) | 404 req/s (higher is better) | JSON p95 ms (lower is better) |
| ---: | --- | ---: | ---: | ---: | ---: | ---: |
| 3 | Plain PHP control | 22,384 | 27,058 | 28,267 | 23,529 | 0.906 |
| 3 | BraidPHP | 9,055 | 8,760 | 10,180 | 10,528 | 2.837 |
| 3 | Slim | 6,219 | 6,719 | 6,330 | 7,363 | 3.616 |
| 3 | Flight | 11,506 | 11,969 | 12,150 | 14,370 | 1.926 |
| 100 | Plain PHP control, no route table | 23,358 | 20,764 | 28,963 | 23,702 | 1.611 |
| 100 | BraidPHP | 5,055 | 6,159 | 5,986 | 7,159 | 4.383 |
| 100 | Slim | 2,785 | 2,886 | 3,137 | 3,241 | 6.863 |
| 100 | Flight | 3,100 | 2,811 | 2,530 | 1,906 | 7.452 |

Flight leads throughput in these three-route workloads; BraidPHP leads these
100-route workloads. That describes this uncached per-request application setup,
not universal framework rankings. Slim's production route cache or different
application lifecycle/registration strategies would change the comparison.

## Matched Feature Profile, Shared Server

The same four workloads are checked in every profile. This JSON summary adds
middleware/hooks, request context, and identical service-resolution work; full
per-workload ranges and resource measurements remain in each raw result table.
CPU and RSS have the definitions and limits in the resource section below.

| Handler routes | Application | JSON req/s (higher is better) | JSON p95 ms (lower is better) | CPU us/request (lower is better) | Mean RSS MiB |
| ---: | --- | ---: | ---: | ---: | ---: |
| 3 | PHP control with Composer/DI | 20,655 | 1.363 | 34.8 | 46.2 |
| 3 | BraidPHP | 8,551 | 2.942 | 102.4 | 40.7 |
| 3 | Slim | 5,605 | 4.028 | 165.8 | 40.6 |
| 3 | Flight | 8,989 | 2.589 | 96.1 | 41.5 |
| 100 | PHP control with Composer/DI, no route table | 20,719 | 1.363 | 34.4 | 45.3 |
| 100 | BraidPHP | 5,753 | 3.313 | 164.3 | 39.8 |
| 100 | Slim | 2,477 | 7.718 | 387.1 | 39.3 |
| 100 | Flight | 2,528 | 8.212 | 380.8 | 38.6 |

These separately timed profiles are not a controlled subtraction of middleware
or DI overhead. Short-run variability and host/process history remain material.

## Native Runtime, Separate Model

BraidPHP bootstraps once per process using `App::listen()`. Its HTTP parser/emitter
and connection logging differ from the shared-server setup. This lifecycle
advantage is not a routing-only speedup or a production deployment recommendation.

| Profile | Handler routes | VUs | Plaintext req/s (higher is better) | JSON req/s (higher is better) | Parameter req/s (higher is better) | 404 req/s (higher is better) | JSON p95 ms (lower is better) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| bare | 3 | 16 | 25,082 | 21,713 | 24,161 | 23,371 | 1.311 |
| bare | 100 | 16 | 22,040 | 23,931 | 17,778 | 22,511 | 0.981 |
| features | 3 | 16 | 25,832 | 21,006 | 20,872 | 25,907 | 1.367 |
| features | 100 | 16 | 20,555 | 20,721 | 20,846 | 21,492 | 1.247 |

## CPU And Memory, JSON At 16 Users

Bare-profile JSON results. Only the PHP server process is measured using Linux `/proc`, not k6, the runner,
or the entire host. CPU 100% means one core. CPU us/request divides server CPU
time by completed measured requests. CPU %, CPU us/request, and mean RSS are
medians of per-run values; sampled peak RSS is the maximum across repeats.

Lower resource cost is better for equivalent work and process history. CPU %
alone is not a ranking: low utilization can also mean fewer requests are served.

| Handler routes | Application | CPU % (context-dependent) | CPU us/request (lower is better) | Mean RSS MiB (lower is better) | Max sampled RSS MiB (lower is better) |
| ---: | --- | ---: | ---: | ---: | ---: |
| 3 | Plain PHP control | 50.8 | 18.6 | 47.2 | 50.7 |
| 3 | BraidPHP, shared server | 88.0 | 101.1 | 41.3 | 42.9 |
| 3 | Slim, shared server | 91.5 | 134.2 | 40.7 | 41.7 |
| 3 | Flight, shared server | 85.3 | 72.2 | 42.5 | 44.4 |
| 100 | Plain PHP control, no route table | 42.6 | 20.9 | 46.1 | 50.5 |
| 100 | BraidPHP, shared server | 93.9 | 153.5 | 39.7 | 40.6 |
| 100 | Slim, shared server | 96.4 | 331.5 | 39.4 | 39.7 |
| 100 | Flight, shared server | 95.1 | 339.7 | 38.7 | 39.1 |
| 3 | BraidPHP, native runtime | 45.9 | 20.6 | 37.0 | 37.1 |
| 100 | BraidPHP, native runtime | 73.5 | 31.3 | 37.0 | 37.0 |

These compare different achieved request rates, not resources at an identical
offered throughput. CPU per request helps distinguish saturation from work per
completed request, but is still specific to these small handlers and deployments.

RSS includes PHP, extensions, and resident shared pages; it is neither private
memory nor framework allocations. Each round reuses a server across workloads,
so retained state and preceding request counts influence later RSS values. The
datasets both use 16 users with the same workload order. Do not interpret lower 100-route RSS as a route-table saving,
or larger baseline RSS as a framework memory advantage. No heap profile or
memory-leak diagnosis is implied.

RSS is sampled approximately every 100ms and can miss brief peaks; it is not a
kernel lifetime high-water mark. CPU counters span measured k6 launch through
exit, including launch/exit idle intervals but excluding server startup/warmup.
Raw samples retain PID start ticks, user/system CPU totals, elapsed windows, and
sample counts. [Measurement details](README.md#server-cpu-and-rss) define the units.

## Warm Router Snapshot

The router reuses immutable route-path segments in a weak map, not matched
parameters or custom-matcher results. Removed routes are not retained by its
keys. Matching stays linear and declaration-ordered. The cache starts cold on
each SAPI request; native mode can reuse it across requests.

Recorded [router.php](router.php) measurement: CPU 2, PHP 8.5.12, OPcache on, JIT and
Xdebug off, five repeats of 2000 dispatches after warmup. The parameter case
matches the last-declared route; setup and HTTP are excluded. These snapshot
measurements do not establish a before/after speedup.

| Routes | Parameter us/dispatch (lower is better) | Missing us/dispatch (lower is better) |
| ---: | ---: | ---: |
| 3 | 1.597 | 0.939 |
| 100 | 21.160 | 14.939 |
| 1000 | 203.613 | 97.702 |

## Evidence And Limits

- [Bare three-route results](results/20261024-1.2.0-final-bare-routes-3/RESULTS.md): 60 samples,
  4,719,386 requests, including ranges, p95/p99, CPU, and RSS.
- [Bare 100-route results](results/20261024-1.2.0-final-bare-routes-100/RESULTS.md): 60 samples,
  3,477,468 requests.
- [Feature three-route results](results/20261024-1.2.0-final-features-routes-3/RESULTS.md): 60 samples,
  4,105,604 requests.
- [Feature 100-route results](results/20261024-1.2.0-final-features-routes-100/RESULTS.md): 60 samples,
  3,185,893 requests.
- Each directory contains metadata, source hashes, raw k6 summaries, samples,
  and subprocess logs. [Router samples](results/20261024-1.2.0-final-router.json)
  include the measured snapshot's source hashes.
- [Release metadata](results/braidphp-k6-latest-release.json),
  and [checksum manifest](results/k6-v2.3.0-checksums.txt) preserve the tooling checks.
  Each recorded run's metadata includes the installed dependency versions.
- Generated evidence is local and Git-ignored. Archive `results/` alongside this
  report when sharing or publishing measurements; links are local until then.
- Bare BraidPHP shared-server JSON throughput ranges from 6,808 to 12,026 req/s with
  three routes and 4,563 to 7,180 req/s with 100 routes. Ranges are not confidence
  intervals. Percentiles are medians of per-run percentiles, not pooled ones, and
  exclude connection setup; throughput includes opening connections.
- Five-second local samples and closed-loop load cannot establish production
  capacity or significance. No DB, isolated DI/upload/logging throughput, private/heap memory,
  production FPM/workers, keep-alive, mixed traffic, slow-client resistance, or
  fixed-arrival-rate overload is measured. No causal speedup is inferred from
  comparing old-k6 and new-k6 runs.

See [README.md](README.md) for exact commands and workload definitions.
