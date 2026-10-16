<?php

namespace App\Ai\Tools;

use App\Services\ReplacementCaseService;
use App\Support\HarborReplacementCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CheckStock implements Tool
{
    public function __construct(
        public string $caseId,
        public int $generation,
    ) {}

    public function description(): Stringable|string
    {
        return 'Check how many of this order\'s item are in stock at the Kent warehouse for this visitor. This does not save a replacement.';
    }

    public function handle(Request $request): Stringable|string
    {
        $activity = app(ReplacementCaseService::class);
        $activity->recordActivity($this->caseId, $this->generation, 'Checking stock');

        $order = HarborReplacementCatalog::find((string) ($request['order_number'] ?? ''));

        if ($order === null) {
            return 'We could not find that order, so stock cannot be checked.';
        }

        $onHand = $activity->onHand($this->caseId, $order['sku']);

        if ($onHand < 1) {
            $activity->recordActivity($this->caseId, $this->generation, 'That item is out of stock.');

            return 'That item is out of stock at Kent.';
        }

        $activity->recordActivity($this->caseId, $this->generation, 'Kent has '.$onHand.' in stock.');

        return 'Kent has '.$onHand.' in stock for '.$order['item'].'.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->required(),
        ];
    }
}
