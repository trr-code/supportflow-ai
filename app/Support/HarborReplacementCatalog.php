<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Fictional Harbor orders for the replacement demo. Dates stay relative so the 5-day window does not rot.
 */
class HarborReplacementCatalog
{
    /**
     * @return list<array{
     *     number: string,
     *     customer_name: string,
     *     email: string,
     *     sku: string,
     *     item: string,
     *     quantity: int,
     *     delivered_days_ago: int,
     *     photos_on_file: bool
     * }>
     */
    public static function orders(): array
    {
        return [
            [
                'number' => 'HB-10482',
                'customer_name' => 'Jamie Chen',
                'email' => 'jamie.chen@example.test',
                'sku' => 'driftwood-duffel',
                'item' => 'Driftwood Duffel',
                'quantity' => 1,
                'delivered_days_ago' => 2,
                'photos_on_file' => true,
            ],
            [
                'number' => 'HB-33001',
                'customer_name' => 'Riley Nguyen',
                'email' => 'riley.nguyen@example.test',
                'sku' => 'driftwood-duffel',
                'item' => 'Driftwood Duffel',
                'quantity' => 1,
                'delivered_days_ago' => 12,
                'photos_on_file' => true,
            ],
            [
                'number' => 'HB-33002',
                'customer_name' => 'Sam Patel',
                'email' => 'sam.patel@example.test',
                'sku' => 'harbor-day-duffel',
                'item' => 'Harbor Day Duffel',
                'quantity' => 1,
                'delivered_days_ago' => 2,
                'photos_on_file' => true,
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function stock(): array
    {
        return [
            'driftwood-duffel' => 4,
            'harbor-day-duffel' => 0,
        ];
    }

    /**
     * @return array{
     *     number: string,
     *     customer_name: string,
     *     email: string,
     *     sku: string,
     *     item: string,
     *     quantity: int,
     *     delivered_days_ago: int,
     *     photos_on_file: bool
     * }|null
     */
    public static function find(string $orderNumber): ?array
    {
        $number = strtoupper(trim($orderNumber));

        foreach (self::orders() as $order) {
            if ($order['number'] === $number) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *     number: string,
     *     customer_name: string,
     *     email: string,
     *     sku: string,
     *     item: string,
     *     quantity: int,
     *     delivered_days_ago: int,
     *     photos_on_file: bool
     * }>
     */
    public static function findByEmail(string $email): array
    {
        $email = strtolower(trim($email));

        return array_values(array_filter(
            self::orders(),
            fn (array $order): bool => $order['email'] === $email,
        ));
    }

    public static function deliveredOn(int $deliveredDaysAgo): CarbonImmutable
    {
        return CarbonImmutable::today()->subDays($deliveredDaysAgo)->startOfDay();
    }
}
