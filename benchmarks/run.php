<?php

declare(strict_types=1);

function median(array $values): float
{
    sort($values, SORT_NUMERIC);
    $middle = intdiv(count($values), 2);
    return count($values) % 2 === 1 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
}

function capture(array $command): string
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start ' . $command[0]);
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Command failed: ' . implode(' ', $command));
    }
    return trim($output);
}

function parseProcessUsage(string $stat, string $status): array
{
    $endOfName = strrpos($stat, ')');
    $fields = $endOfName === false ? [] : preg_split('/\s+/', trim(substr($stat, $endOfName + 1)));
    if (
        count($fields) < 20 || !ctype_digit($fields[11]) || !ctype_digit($fields[12])
        || !ctype_digit($fields[19])
        || preg_match('/^VmRSS:\s+(\d+)\s+kB$/m', $status, $memory) !== 1
    ) {
        throw new UnexpectedValueException('Invalid Linux process CPU/RSS counters.');
    }
    return [
        'cpu_ticks' => (int) $fields[11] + (int) $fields[12],
        'start_ticks' => (int) $fields[19],
        'rss_kib' => (int) $memory[1],
    ];
}

function processUsage(int $pid): array
{
    $stat = @file_get_contents('/proc/' . $pid . '/stat');
    $status = @file_get_contents('/proc/' . $pid . '/status');
    if ($stat === false || $status === false) {
        throw new UnexpectedValueException('Cannot read server process counters for PID ' . $pid);
    }
    return [...parseProcessUsage($stat, $status), 'time_ns' => hrtime(true)];
}

function resourceMetrics(array $start, array $end, array $rssSamples, int $ticksPerSecond, int $requests): array
{
    $seconds = ($end['time_ns'] - $start['time_ns']) / 1_000_000_000;
    $ticks = $end['cpu_ticks'] - $start['cpu_ticks'];
    if (
        $start['start_ticks'] !== $end['start_ticks'] || $ticks < 0 || $seconds <= 0
        || $ticksPerSecond <= 0 || $requests <= 0 || $rssSamples === []
    ) {
        throw new UnexpectedValueException('Invalid server resource measurement window.');
    }
    $cpuSeconds = $ticks / $ticksPerSecond;
    return [
        'server_cpu_seconds' => $cpuSeconds,
        'server_cpu_percent' => 100 * $cpuSeconds / $seconds,
        'server_cpu_us_per_request' => 1_000_000 * $cpuSeconds / $requests,
        'server_rss_mean_mib' => array_sum($rssSamples) / count($rssSamples) / 1024,
        'server_rss_peak_mib' => max($rssSamples) / 1024,
        'resource_window_seconds' => $seconds,
        'resource_samples' => count($rssSamples),
        'resource_start' => $start,
        'resource_end' => $end,
    ];
}

if (in_array('--self-test', $argv, true)) {
    foreach ([[[9, 1, 5], 5.0], [[8, 2, 4, 6], 5.0], [[3], 3.0]] as [$values, $expected]) {
        if (median($values) !== $expected) {
            throw new RuntimeException('Median self-test failed.');
        }
    }
    $stat = '42 (server (test) name) S ' . implode(' ', array_fill(0, 10, '0')) . ' 120 30 '
        . implode(' ', array_fill(0, 6, '0')) . ' 999';
    if (parseProcessUsage($stat, "Name:\tphp\nVmRSS:\t2048 kB\n") !== ['cpu_ticks' => 150, 'start_ticks' => 999, 'rss_kib' => 2048]) {
        throw new UnexpectedValueException('Process counter parsing self-test failed.');
    }
    $start = ['cpu_ticks' => 100, 'start_ticks' => 999, 'time_ns' => 1_000_000_000];
    $end = ['cpu_ticks' => 200, 'start_ticks' => 999, 'time_ns' => 3_000_000_000];
    $resources = resourceMetrics($start, $end, [1024, 3072], 100, 1000);
    foreach (['server_cpu_percent' => 50.0, 'server_cpu_us_per_request' => 1000.0, 'server_rss_mean_mib' => 2.0, 'server_rss_peak_mib' => 3.0] as $metric => $expected) {
        if (abs($resources[$metric] - $expected) > 0.000001) {
            throw new UnexpectedValueException('Resource calculation self-test failed: ' . $metric);
        }
    }
    $rejectedCases = 0;
    foreach ([['invalid', "VmRSS:\t1 kB\n"], [$stat, 'missing RSS']] as [$invalidStat, $invalidStatus]) {
        try {
            parseProcessUsage($invalidStat, $invalidStatus);
            throw new LogicException('Malformed process counters were accepted.');
        } catch (UnexpectedValueException) {
            ++$rejectedCases;
        }
    }
    try {
        resourceMetrics($start, [...$end, 'start_ticks' => 1000], [1024], 100, 1000);
        throw new LogicException('A changed server PID identity was accepted.');
    } catch (UnexpectedValueException) {
        ++$rejectedCases;
    }
    if ($rejectedCases !== 3) {
        throw new LogicException('Resource rejection self-tests failed.');
    }
    echo "Self-test passed.\n";
    exit(0);
}

$directory = __DIR__;
$output = getenv('OUTPUT_DIR') ?: $directory . '/results/' . gmdate('Ymd-His');
$k6 = getenv('K6_BIN') ?: 'k6';
$duration = getenv('DURATION') ?: '10s';
$repeats = filter_var(getenv('REPEATS') ?: '3', FILTER_VALIDATE_INT);
$concurrency = array_map('intval', explode(',', getenv('VUS') ?: '1,16'));
$port = filter_var(getenv('BENCH_PORT') ?: '8091', FILTER_VALIDATE_INT);
$routeCount = filter_var(getenv('ROUTE_COUNT') ?: '3', FILTER_VALIDATE_INT);
if (
    !preg_match('/^[1-9][0-9]*s$/D', $duration)
    || $repeats === false || $repeats < 1
    || min($concurrency) < 1
    || $port === false || $port < 1024 || $port > 65535
    || $routeCount === false || $routeCount < 3 || $routeCount > 10_000
) {
    throw new InvalidArgumentException('Use positive integer REPEATS/VUS, DURATION in seconds, ROUTE_COUNT from 3 to 10000, and a port from 1024 to 65535.');
}
if (!is_file($directory . '/vendor/autoload.php')) {
    throw new RuntimeException('Run composer install in benchmarks first.');
}
$ticksPerSecond = (int) capture(['getconf', 'CLK_TCK']);
if ($ticksPerSecond <= 0) {
    throw new UnexpectedValueException('Linux clock-tick rate must be positive.');
}
processUsage(getmypid());
if (is_dir($output) || !mkdir($output, 0777, true)) {
    throw new RuntimeException('OUTPUT_DIR must be a new directory.');
}

$environment = array_merge(getenv(), ['XDEBUG_MODE' => 'off']);
$environment['ROUTE_COUNT'] = (string) $routeCount;
unset($environment['PHP_CLI_SERVER_WORKERS']);
$php = [
    PHP_BINARY, '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=0',
    '-d', 'opcache.file_update_protection=0', '-d', 'opcache.jit=disable',
    '-d', 'display_errors=stderr', '-d', 'zend.assertions=-1',
];
$serverPrefix = getenv('SERVER_CPU') !== false ? ['taskset', '-c', getenv('SERVER_CPU')] : [];
$loadPrefix = getenv('LOAD_CPUS') !== false ? ['taskset', '-c', getenv('LOAD_CPUS')] : [];
$targets = ['baseline', 'braidphp-sapi', 'slim', 'flight', 'braidphp-native'];
$workloads = ['plaintext', 'json', 'parameter', 'missing'];
$metadata = [
    'started_utc' => gmdate(DATE_ATOM),
    'php' => capture([PHP_BINARY, '-v']),
    'k6' => capture([$k6, 'version']),
    'k6_binary_sha256' => hash_file('sha256', trim(capture(['which', $k6]))),
    'composer' => capture(['composer', '--version']),
    'os' => php_uname(),
    'cpu' => capture(['lscpu']),
    'commit' => capture(['git', '-C', dirname($directory), 'rev-parse', 'HEAD']),
    'dirty_status' => capture(['git', '-C', dirname($directory), 'status', '--short']),
    'source_diff_sha256' => hash('sha256', capture(['git', '-C', dirname($directory), 'diff', '--', 'src', 'composer.json'])),
    'source_sha256' => [],
    'peer_lock_sha256' => hash_file('sha256', $directory . '/composer.lock'),
    'autoload_classmap_sha256' => hash_file('sha256', $directory . '/vendor/composer/autoload_classmap.php'),
    'dependencies' => json_decode(capture(['composer', '--working-dir=' . $directory, 'show', '--format=json']), true, 512, JSON_THROW_ON_ERROR),
    'braidphp_dependencies' => json_decode(capture(['composer', '--working-dir=' . dirname($directory), 'show', '--format=json']), true, 512, JSON_THROW_ON_ERROR),
    'php_command' => $php,
    'php_runtime' => json_decode(capture([...$php, '-r', 'echo json_encode(["opcache_enabled" => opcache_get_status(false)["opcache_enabled"], "jit" => ini_get("opcache.jit"), "xdebug_mode" => function_exists("xdebug_info") ? xdebug_info("mode") : []]);']), true, 512, JSON_THROW_ON_ERROR),
    'server_cpu' => getenv('SERVER_CPU') ?: 'not pinned',
    'load_cpus' => getenv('LOAD_CPUS') ?: 'not pinned',
    'duration' => $duration,
    'warmup' => '1s per workload and concurrency before every measured run',
    'repeats' => $repeats,
    'vus' => $concurrency,
    'handler_routes' => $routeCount,
    'connection_reuse' => false,
    'resources' => [
        'source' => 'Linux /proc/<server-pid>/stat and status; PHP server process only',
        'clock_ticks_per_second' => $ticksPerSecond,
        'rss_sample_interval_ms' => 100,
        'cpu_percent_basis' => '100% is one CPU core, not the whole host',
        'window' => 'after warmup, from k6 process launch until exit; excludes server startup',
        'memory' => 'sampled RSS including PHP, extensions and resident shared pages; not private memory or PHP heap',
    ],
    'target_order' => [],
];
$sources = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname($directory) . '/src'));
foreach ($sources as $source) {
    if ($source->isFile() && $source->getExtension() === 'php') {
        $metadata['source_sha256'][substr($source->getPathname(), strlen(dirname($directory)) + 1)] = hash_file('sha256', $source->getPathname());
    }
}
ksort($metadata['source_sha256']);
file_put_contents($output . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
$samples = [];

for ($round = 1; $round <= $repeats; $round++) {
    $offset = ($round - 1) % count($targets);
    $order = array_merge(array_slice($targets, $offset), array_slice($targets, 0, $offset));
    $metadata['target_order'][] = $order;
    foreach ($order as $target) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errorNumber, $errorMessage, 0.2);
        if (is_resource($socket)) {
            fclose($socket);
            throw new RuntimeException('Benchmark port is occupied; choose another BENCH_PORT.');
        }
        $serverEnvironment = array_merge($environment, [
            'BENCH_FRAMEWORK' => $target,
            'BENCH_NATIVE' => $target === 'braidphp-native' ? '1' : '0',
            'BENCH_PORT' => (string) $port,
        ]);
        $entry = $directory . ($target === 'braidphp-native' || $target === 'braidphp-sapi' ? '/braidphp.php' : '/peers.php');
        $arguments = $target === 'braidphp-native' ? [$entry] : ['-S', '127.0.0.1:' . $port, $entry];
        $server = proc_open(
            [...$serverPrefix, ...$php, ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', "$output/$target-$round.server.log", 'a'], 2 => ['file', "$output/$target-$round.server.log", 'a']],
            $pipes,
            $directory,
            $serverEnvironment
        );
        if (!is_resource($server)) {
            throw new RuntimeException('Cannot start benchmark server.');
        }
        try {
            $ready = false;
            $context = stream_context_create(['http' => ['timeout' => 0.2]]);
            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline) {
                if (@file_get_contents("http://127.0.0.1:$port/plaintext", false, $context) === 'Hello, World!') {
                    $ready = true;
                    break;
                }
                if (!proc_get_status($server)['running']) {
                    break;
                }
            }
            if (!$ready) {
                throw new RuntimeException("$target did not become ready; inspect its server log.");
            }
            $serverPid = proc_get_status($server)['pid'];
            foreach ($concurrency as $vus) {
                foreach ($workloads as $workload) {
                    $stem = "$target-$workload-vus$vus-run$round";
                    foreach (['warmup' => '1s', 'measure' => $duration] as $phase => $phaseDuration) {
                        $summary = "$output/$stem-$phase.json";
                        $resourceStart = $phase === 'measure' ? processUsage($serverPid) : null;
                        $rssSamples = $resourceStart === null ? [] : [$resourceStart['rss_kib']];
                        $load = proc_open(
                            [...$loadPrefix, $k6, 'run', '--quiet', $directory . '/http.js'],
                            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', "$output/$stem-$phase.log", 'a']],
                            $loadPipes,
                            $directory,
                            array_merge($environment, [
                                'BASE_URL' => "http://127.0.0.1:$port", 'WORKLOAD' => $workload,
                                'VUS' => (string) $vus, 'DURATION' => $phaseDuration, 'SUMMARY' => $summary,
                                'K6_NO_USAGE_REPORT' => 'true',
                            ])
                        );
                        if (!is_resource($load)) {
                            throw new UnexpectedValueException('Cannot start k6.');
                        }
                        try {
                            stream_set_blocking($loadPipes[1], false);
                            while (!feof($loadPipes[1])) {
                                $read = [$loadPipes[1]];
                                $write = null;
                                $except = null;
                                if (stream_select($read, $write, $except, 0, 100_000) === false) {
                                    throw new UnexpectedValueException('Cannot monitor k6 output.');
                                }
                                if ($read !== []) {
                                    file_put_contents("$output/$stem-$phase.log", stream_get_contents($loadPipes[1]), FILE_APPEND);
                                }
                                if ($phase === 'measure') {
                                    $rssSamples[] = processUsage($serverPid)['rss_kib'];
                                }
                            }
                            $resourceEnd = $phase === 'measure' ? processUsage($serverPid) : null;
                        } catch (Throwable $exception) {
                            proc_terminate($load);
                            throw $exception;
                        } finally {
                            fclose($loadPipes[1]);
                            $loadExitCode = proc_close($load);
                        }
                        if ($loadExitCode !== 0) {
                            throw new RuntimeException("k6 failed: $stem ($phase); inspect its log and summary.");
                        }
                    }
                    $data = json_decode(file_get_contents($summary), true, 512, JSON_THROW_ON_ERROR);
                    $metrics = $data['metrics'];
                    $sample = [
                        'target' => $target, 'workload' => $workload, 'vus' => $vus, 'round' => $round,
                        'requests_per_second' => $metrics['http_reqs']['values']['rate'],
                        'requests' => $metrics['http_reqs']['values']['count'],
                        'p95_ms' => $metrics['http_req_duration']['values']['p(95)'],
                        'p99_ms' => $metrics['http_req_duration']['values']['p(99)'],
                        'failed_requests' => $metrics['http_req_failed']['values']['passes'],
                        'failed_checks' => $metrics['checks']['values']['fails'],
                        ...resourceMetrics($resourceStart, $resourceEnd, $rssSamples, $ticksPerSecond, $metrics['http_reqs']['values']['count']),
                    ];
                    $samples[] = $sample;
                    file_put_contents($output . '/samples.json', json_encode($samples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
                    printf("%d/%d %-15s %-9s VUs=%-2d %8.0f req/s p95=%.3fms CPU=%.1f%% RSS=%.1fMiB\n", $round, $repeats, $target, $workload, $vus, $sample['requests_per_second'], $sample['p95_ms'], $sample['server_cpu_percent'], $sample['server_rss_mean_mib']);
                }
            }
        } finally {
            proc_terminate($server);
            proc_close($server);
        }
    }
}

$metadata['finished_utc'] = gmdate(DATE_ATOM);
file_put_contents($output . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
$report = "# HTTP Benchmark Results\n\n";
$report .= "{$metadata['started_utc']}; $repeats repeats x $duration per cell, 1s warmup before each run.\n\n";
$report .= "$routeCount handler routes per framework; plain PHP is a no-framework control without a route table.\n\n";
$report .= "Median throughput and median per-run percentiles; ranges are min-max throughput, not confidence intervals.\n\n";
$report .= "Server CPU only (100% = one core), CPU microseconds per request, mean sampled RSS and maximum sampled RSS. RSS is sampled every approximately 100ms; short peaks may be missed. Resource windows include k6 launch/exit but exclude server startup and warmup.\n\n";
$report .= "Lower resource cost is better for equivalent work and process history. CPU % alone is not a ranking: low utilization can also mean fewer requests are served. VUs describe the test, not a score.\n\n";
$report .= "| Target | Workload | VUs | Median req/s (higher is better) | Min-max req/s (higher is better) | p95 ms (lower is better) | p99 ms (lower is better) | CPU % (context-dependent) | CPU us/req (lower is better) | Mean RSS MiB (lower is better) | Max sampled RSS MiB (lower is better) | Errors/check failures (lower is better) |\n";
$report .= "| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |\n";
foreach ($targets as $target) {
    foreach ($concurrency as $vus) {
        foreach ($workloads as $workload) {
            $group = array_values(array_filter($samples, static fn (array $sample): bool => $sample['target'] === $target && $sample['vus'] === $vus && $sample['workload'] === $workload));
            $rates = array_column($group, 'requests_per_second');
            $report .= sprintf(
                "| %s | %s | %d | %.0f | %.0f-%.0f | %.3f | %.3f | %.1f | %.1f | %.1f | %.1f | %d/%d |\n",
                $target, $workload, $vus, median($rates), min($rates), max($rates),
                median(array_column($group, 'p95_ms')), median(array_column($group, 'p99_ms')),
                median(array_column($group, 'server_cpu_percent')), median(array_column($group, 'server_cpu_us_per_request')),
                median(array_column($group, 'server_rss_mean_mib')), max(array_column($group, 'server_rss_peak_mib')),
                array_sum(array_column($group, 'failed_requests')), array_sum(array_column($group, 'failed_checks'))
            );
        }
    }
}
file_put_contents($output . '/RESULTS.md', $report);
echo "Results: $output/RESULTS.md\n";
