<?php

namespace App\Services;

use App\Support\HarborReplacementCatalog;
use Carbon\CarbonImmutable;

/**
 * Arrival-damage replacement from the published Harbor article.
 *
 * File within 5 days, with photos of the carton and item. A replacement can be
 * prepared only when that filing is on time and the same item is in stock at Kent.
 */
class DamageReplacementPolicy
{
    public const WINDOW_DAYS = 5;

    /**
     * @param  array{
     *     number: string,
     *     customer_name: string,
     *     email: string,
     *     sku: string,
     *     item: string,
     *     quantity: int,
     *     delivered_days_ago: int,
     *     photos_on_file: bool
     * }  $order
     * @return array{eligible: bool, summary: string}
     */
    public function assess(array $order, int $onHand): array
    {
        if (! $order['photos_on_file']) {
            return [
                'eligible' => false,
                'summary' => 'Photos of the carton and the item are still needed, so a replacement cannot be prepared.',
            ];
        }

        $deliveredOn = HarborReplacementCatalog::deliveredOn($order['delivered_days_ago']);
        $earliest = CarbonImmutable::today()->subDays(self::WINDOW_DAYS)->startOfDay();

        if ($deliveredOn->lt($earliest)) {
            return [
                'eligible' => false,
                'summary' => 'That order is outside the 5-day window, so a replacement cannot be prepared.',
            ];
        }

        if ($order['quantity'] !== 1) {
            return [
                'eligible' => false,
                'summary' => 'A replacement can be prepared for one item only.',
            ];
        }

        if ($onHand < 1) {
            return [
                'eligible' => false,
                'summary' => 'That item is out of stock, so a replacement cannot be prepared.',
            ];
        }

        return [
            'eligible' => true,
            'summary' => 'A replacement can be prepared for you to confirm.',
        ];
    }
}
