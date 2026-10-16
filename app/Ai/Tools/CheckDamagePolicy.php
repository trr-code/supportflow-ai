<?php

namespace App\Ai\Tools;

use App\Services\DamageReplacementPolicy;
use App\Services\ReplacementCaseService;
use App\Support\HarborReplacementCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CheckDamagePolicy implements Tool
{
    public function __construct(
        public string $caseId,
        public int $generation,
    ) {}

    public function description(): Stringable|string
    {
        return 'Check whether a fictional Harbor order can have a damaged-on-arrival replacement prepared. Use the order number from the lookup. This does not save a replacement.';
    }

    public function handle(Request $request): Stringable|string
    {
        $activity = app(ReplacementCaseService::class);
        $activity->recordActivity($this->caseId, $this->generation, 'Checking the damage policy');

        $order = HarborReplacementCatalog::find((string) ($request['order_number'] ?? ''));

        if ($order === null) {
            return 'We could not find that order, so the damage policy cannot be checked.';
        }

        $assessment = app(DamageReplacementPolicy::class)->assess(
            $order,
            $activity->onHand($this->caseId, $order['sku']),
        );

        return $assessment['summary'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->required(),
        ];
    }
}
