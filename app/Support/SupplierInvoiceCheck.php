<?php

namespace App\Support;

final class SupplierInvoiceCheck
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{message: string, target?: string, label?: string}>
     */
    public function inspect(array $lines, mixed $taxCents, mixed $statedTotalCents, string $currency): array
    {
        $messages = [];
        $computed = 0;

        if ($lines === []) {
            $messages[] = ['message' => 'Add at least one line.'];
        }

        foreach ($lines as $index => $line) {
            $quantity = $this->cents($line['quantity'] ?? null);
            $unitPrice = $this->cents($line['unit_price_cents'] ?? null);
            $stated = $this->cents($line['stated_line_total_cents'] ?? null);
            $number = $index + 1;

            if ($quantity === null || $unitPrice === null || $stated === null) {
                $messages[] = ['message' => "Line {$number} needs a quantity, unit price, and line total."];

                continue;
            }

            $expected = $quantity * $unitPrice;
            $computed += $expected;

            if ($expected !== $stated) {
                $entered = InvoiceMoney::format($stated, $currency);
                $calculated = InvoiceMoney::format($expected, $currency);
                $messages[] = [
                    'label' => "Go to Line {$number}",
                    'target' => "invoice-line-total-{$number}",
                    'message' => "Check Line {$number} under Line items. Line total is {$entered}. Quantity × unit price gives {$calculated}.",
                ];
            }
        }

        $tax = $this->cents($taxCents);
        $statedTotal = $this->cents($statedTotalCents);

        if ($tax === null || $statedTotal === null) {
            $messages[] = ['message' => 'Tax and the invoice total must be zero or a positive amount.'];

            return $messages;
        }

        if (($computed + $tax) !== $statedTotal) {
            $entered = InvoiceMoney::format($statedTotal, $currency);
            $calculated = InvoiceMoney::format($computed + $tax, $currency);
            $messages[] = [
                'label' => 'Go to Invoice total',
                'target' => 'invoice-total',
                'message' => "Check Invoice total above Line items. Invoice total is {$entered}. Recalculating the lines and adding tax gives {$calculated}.",
            ];
        }

        return $messages;
    }

    public function cents(mixed $value, int $max = 100_000_000): ?int
    {
        if (is_int($value) || is_float($value)) {
            if (! is_finite((float) $value) || (float) $value < 0 || floor((float) $value) !== (float) $value) {
                return null;
            }

            $cents = (int) $value;

            return $cents <= $max ? $cents : null;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $cents = (int) $value;

            return $cents <= $max ? $cents : null;
        }

        return null;
    }
}
