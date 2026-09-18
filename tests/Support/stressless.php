<?php

use Pest\Stressless\Result;

use function Pest\Stressless\stress;

function stressTestingEnabled(): bool
{
    $url = getenv('STRESS_URL');

    return filter_var(getenv('STRESS'), FILTER_VALIDATE_BOOL)
        && is_string($url)
        && $url !== '';
}

function stressBaseUrl(): string
{
    $url = getenv('STRESS_URL');

    if (! is_string($url) || $url === '') {
        throw new RuntimeException('STRESS_URL is required when STRESS=true.');
    }

    return rtrim($url, '/');
}

function stressTargetUrl(string $path): string
{
    return stressBaseUrl().($path === '/' ? '/' : $path);
}

/**
 * @return array<string, string>
 */
function stressRepresentativeRoutes(): array
{
    return [
        'home' => '/',
        'knowledge index' => '/knowledge',
        'return window' => '/knowledge/return-window',
    ];
}

/**
 * @return list<int>
 */
function stressConcurrencyLadder(): array
{
    $levels = [2, 4, 8, 12, 16, 24, 32, 48, 64, 96, 128];
    $max = stressMaxConcurrency();

    if ($max === null) {
        return $levels;
    }

    return array_values(array_filter($levels, fn (int $level): bool => $level <= $max));
}

function stressMaxConcurrency(): ?int
{
    $raw = getenv('STRESS_MAX_CONCURRENCY');

    if (! is_string($raw) || $raw === '') {
        return null;
    }

    $max = (int) $raw;

    return $max > 0 ? $max : null;
}

function persistStresslessCookiesAcrossIterations(): void
{
    putenv('K6_NO_COOKIES_RESET=true');
    $_ENV['K6_NO_COOKIES_RESET'] = 'true';
    $_SERVER['K6_NO_COOKIES_RESET'] = 'true';
}

function runStressGet(string $path, int $concurrency, int $seconds): Result
{
    persistStresslessCookiesAcrossIterations();

    return stress(stressTargetUrl($path))
        ->concurrently(requests: $concurrency)
        ->for($seconds)
        ->seconds()
        ->get()
        ->run();
}

/**
 * @return array{
 *     path: string,
 *     concurrency: int,
 *     duration_ms: float,
 *     count: int,
 *     failed: int,
 *     success: int,
 *     success_percent: float,
 *     failure_percent: float,
 *     requests_per_second: float,
 *     successful_requests_per_second: float,
 *     p95_ms: float,
 *     ttfb_p95_ms: float
 * }
 */
function stressMetrics(string $path, Result $result): array
{
    $count = $result->requests()->count();
    $failed = $result->requests()->failed()->count();
    $success = $count - $failed;
    $durationMs = $result->testRun()->duration();
    $durationSeconds = $durationMs > 0 ? $durationMs / 1000 : 0.0;

    return [
        'path' => $path,
        'concurrency' => $result->testRun()->concurrency(),
        'duration_ms' => $durationMs,
        'count' => $count,
        'failed' => $failed,
        'success' => $success,
        'success_percent' => $count > 0 ? ($success / $count) * 100 : 0.0,
        'failure_percent' => $count > 0 ? ($failed / $count) * 100 : 0.0,
        'requests_per_second' => $result->requests()->rate(),
        'successful_requests_per_second' => $durationSeconds > 0 ? $success / $durationSeconds : 0.0,
        'p95_ms' => $result->requests()->duration()->p95(),
        'ttfb_p95_ms' => $result->requests()->ttfb()->duration()->p95(),
    ];
}

/**
 * @param  array<string, mixed>  $metrics
 */
function reportStressMetrics(string $label, array $metrics): void
{
    fwrite(STDERR, $label.' '.json_encode($metrics, JSON_THROW_ON_ERROR).PHP_EOL);
}

/**
 * @param  array{count: int, failed: int}  $metrics
 */
function stressPlateauUnhealthy(array $metrics): bool
{
    return $metrics['count'] < 1 || $metrics['failed'] > 0;
}
