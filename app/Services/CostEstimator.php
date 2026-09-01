<?php

namespace App\Services;

class CostEstimator
{
    public function estimate(string $model, ?int $inputTokens, ?int $outputTokens): ?float
    {
        $rates = $this->rates($model);

        if ($rates === null || $inputTokens === null || ! isset($rates['input'])) {
            return null;
        }

        $outputTokens ??= 0;

        return round(
            (($inputTokens / 1_000_000) * (float) $rates['input']) + (($outputTokens / 1_000_000) * (float) ($rates['output'] ?? 0)),
            4,
        );
    }

    public function estimateMinutes(string $model, ?float $minutes): ?float
    {
        $rates = $this->rates($model);

        if ($rates === null || $minutes === null || ! isset($rates['per_minute'])) {
            return null;
        }

        return round($minutes * (float) $rates['per_minute'], 4);
    }

    /**
     * @return array<string, float>|null
     */
    protected function rates(string $model): ?array
    {
        /** @var array<string, mixed> $pricing */
        $pricing = config('supportflow.pricing', []);
        $table = is_array($pricing['models'] ?? null) ? $pricing['models'] : $pricing;

        /** @var array<string, float>|null $rates */
        $rates = $table[$model] ?? null;

        return is_array($rates) ? $rates : null;
    }
}
