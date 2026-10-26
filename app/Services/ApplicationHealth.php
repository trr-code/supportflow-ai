<?php

namespace App\Services;

use App\Exceptions\ApplicationUnhealthyException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplicationHealth
{
    public const string CACHE_KEY = 'supportflow:queue-heartbeat';

    public function assertHealthy(): void
    {
        $this->pingDatabase();
        $this->assertQueueHeartbeat();
    }

    public function pingDatabase(): void
    {
        try {
            DB::connection()->select('select 1');
        } catch (Throwable $exception) {
            throw new ApplicationUnhealthyException('Database is unavailable.', previous: $exception);
        }
    }

    public function assertQueueHeartbeat(): void
    {
        $payload = Cache::get(self::CACHE_KEY);

        if (! is_array($payload)) {
            throw new ApplicationUnhealthyException('Queue worker heartbeat is missing.');
        }

        $at = $payload['at'] ?? null;

        if (! is_string($at) || $at === '') {
            throw new ApplicationUnhealthyException('Queue worker heartbeat is missing.');
        }

        $recorded = CarbonImmutable::parse($at);
        $maxAge = max(1, (int) config('supportflow.health.heartbeat_max_age_seconds', 90));

        if ($recorded->lt(now()->subSeconds($maxAge))) {
            throw new ApplicationUnhealthyException('Queue worker heartbeat is stale.');
        }

        $queues = $payload['queues'] ?? null;

        if (! is_array($queues)) {
            throw new ApplicationUnhealthyException('Queue worker is not subscribed to the required queues.');
        }

        $present = array_map(
            static fn (mixed $queue): string => strtolower(trim((string) $queue)),
            $queues,
        );

        foreach ($this->requiredQueues() as $required) {
            if (! in_array($required, $present, true)) {
                throw new ApplicationUnhealthyException('Queue worker is not subscribed to the required queues.');
            }
        }
    }

    /**
     * @param  list<string>  $queues
     */
    public function record(string $connection, array $queues, bool $mergeExisting = false): void
    {
        $queues = $this->normalizeQueues($queues);

        if ($mergeExisting) {
            $existing = Cache::get(self::CACHE_KEY);
            $cachedQueues = is_array($existing) ? ($existing['queues'] ?? null) : null;
            $previous = is_array($cachedQueues)
                ? $this->normalizeQueues(array_values($cachedQueues))
                : [];
            $queues = $this->normalizeQueues([...$previous, ...$queues]);
        }

        $ttl = max(
            (int) config('supportflow.health.heartbeat_max_age_seconds', 90) + 30,
            (int) config('supportflow.health.heartbeat_ttl_seconds', 86_400),
        );

        Cache::put(self::CACHE_KEY, [
            'at' => now()->toIso8601String(),
            'connection' => $connection,
            'queues' => $queues,
        ], now()->addSeconds($ttl));
    }

    /**
     * @return list<string>
     */
    public function parseQueueList(string $queue): array
    {
        return $this->normalizeQueues(explode(',', $queue));
    }

    /**
     * @return list<string>
     */
    public function requiredQueues(): array
    {
        /** @var list<string> $queues */
        $queues = config('supportflow.health.required_queues', ['ai', 'default']);

        return $this->normalizeQueues($queues);
    }

    /**
     * @param  list<mixed>  $queues
     * @return list<string>
     */
    protected function normalizeQueues(array $queues): array
    {
        $normalized = [];

        foreach ($queues as $queue) {
            $name = strtolower(trim((string) $queue));

            if ($name === '' || in_array($name, $normalized, true)) {
                continue;
            }

            $normalized[] = $name;
        }

        return $normalized;
    }
}
