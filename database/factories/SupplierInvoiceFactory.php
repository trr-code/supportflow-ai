<?php

namespace Database\Factories;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => hash('sha256', 'invoice-browser-token-placeholder-value-0001'),
            'original_name' => 'invoice.pdf',
            'storage_path' => 'supplier-invoices/example.pdf',
            'byte_size' => 128,
            'status' => SupplierInvoiceStatus::NeedsReview,
            'extraction_generation' => fake()->uuid(),
            'failure_reason' => null,
            'supplier_name' => 'Northwind Supply',
            'invoice_number' => 'INV-1001',
            'issued_on' => '2026-03-01',
            'currency' => 'USD',
            'tax_cents' => 200,
            'stated_total_cents' => 3200,
            'confidence' => 0.2,
            'confirmed_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => SupplierInvoiceStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
