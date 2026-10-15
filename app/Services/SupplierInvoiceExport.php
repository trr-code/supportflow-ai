<?php

namespace App\Services;

use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Support\InvoiceMoney;

class SupplierInvoiceExport
{
    public function csv(SupplierInvoice $invoice): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, [
            'Supplier',
            'Invoice number',
            'Date',
            'Currency',
            'Tax',
            'Invoice total',
            'Line description',
            'Quantity',
            'Unit price',
            'Line total',
        ], escape: '\\');

        foreach ($invoice->lines as $line) {
            fputcsv($handle, [
                $invoice->supplier_name,
                $invoice->invoice_number,
                $invoice->issued_on?->toDateString(),
                $invoice->currency,
                $this->amount($invoice->tax_cents),
                $this->amount($invoice->stated_total_cents),
                $line->description,
                $line->quantity,
                InvoiceMoney::decimal($line->unit_price_cents),
                InvoiceMoney::decimal($line->stated_line_total_cents),
            ], escape: '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    private function amount(?int $cents): string
    {
        return $cents === null ? '' : InvoiceMoney::decimal($cents);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(SupplierInvoice $invoice): array
    {
        return [
            'supplier_name' => $invoice->supplier_name,
            'invoice_number' => $invoice->invoice_number,
            'issued_on' => $invoice->issued_on?->toDateString(),
            'currency' => $invoice->currency,
            'tax_cents' => $invoice->tax_cents,
            'stated_total_cents' => $invoice->stated_total_cents,
            'lines' => $invoice->lines->map(fn (SupplierInvoiceLine $line): array => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'stated_line_total_cents' => $line->stated_line_total_cents,
            ])->values()->all(),
        ];
    }

    public function filename(SupplierInvoice $invoice, string $extension): string
    {
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $invoice->invoice_number) ?: 'invoice';

        return 'invoice-'.$number.'.'.$extension;
    }
}
