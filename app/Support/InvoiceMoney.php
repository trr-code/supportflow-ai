<?php

namespace App\Support;

final class InvoiceMoney
{
    /**
     * Convert a decimal currency amount to integer cents.
     *
     * Integers are whole currency units, so 32 is 3200 cents. Floats are rejected
     * so binary values such as 19.99 are never rounded into the wrong cent.
     */
    public static function toCents(mixed $value, int $max = 100_000_000): ?int
    {
        if (is_int($value)) {
            if ($value < 0) {
                return null;
            }

            $normalized = (string) $value;
        } elseif (is_string($value)) {
            $normalized = trim($value);
        } else {
            return null;
        }

        if (str_starts_with($normalized, '$')) {
            $normalized = trim(substr($normalized, 1));
        }

        if (str_contains($normalized, ',')) {
            if (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $normalized) !== 1) {
                return null;
            }

            $normalized = str_replace(',', '', $normalized);
        }

        if (preg_match('/^(\d*)(?:\.(\d{1,2}))?$/', $normalized, $matches) !== 1 || $normalized === '' || $normalized === '.') {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        if (strlen($whole) > 7) {
            return null;
        }

        $cents = ((int) $whole * 100) + (int) $fraction;

        return $cents <= $max ? $cents : null;
    }

    public static function decimal(int $cents): string
    {
        $whole = intdiv($cents, 100);
        $fraction = $cents % 100;

        return $whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public static function format(int $cents, string $currency = 'USD'): string
    {
        $amount = self::decimal($cents);

        if (strtoupper(trim($currency)) === 'USD') {
            return '$'.$amount;
        }

        return $amount;
    }
}
