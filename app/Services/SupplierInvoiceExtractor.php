<?php

namespace App\Services;

use App\Ai\Agents\SupplierInvoiceExtractionAgent;
use App\Enums\AiRunFeature;
use App\Enums\SupplierInvoiceStatus;
use App\Exceptions\DocumentExtractException;
use App\Models\SupplierInvoice;
use App\Support\DocumentTextExtractor;
use App\Support\SupplierInvoiceCheck;
use App\Support\UntrustedContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class SupplierInvoiceExtractor
{
    public function __construct(
        private readonly DocumentTextExtractor $documents,
        private readonly SupplierInvoiceCheck $checks,
        private readonly AiUsageRecorder $recorder,
    ) {}

    public function extract(string $invoiceId, string $generation): void
    {
        $invoice = $this->current($invoiceId, $generation);

        if ($invoice === null) {
            return;
        }

        try {
            $text = $this->documents->extract(
                Storage::disk('local')->path($invoice->storage_path),
                'pdf',
            );
        } catch (DocumentExtractException $exception) {
            $this->fail($invoiceId, $generation, $exception->getMessage());

            return;
        }

        if ($this->current($invoiceId, $generation) === null) {
            return;
        }

        $model = (string) config('supportflow.models.invoice');
        $run = $this->recorder->start(AiRunFeature::InvoiceExtraction, null, $model);

        try {
            $response = SupplierInvoiceExtractionAgent::make()->prompt(
                "Copy the printed invoice fields into the schema. Do not recalculate totals.\n\n"
                .UntrustedContent::wrap('supplier_invoice', $text),
                provider: Lab::OpenAI,
                model: $model,
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->recorder->fail($run, $exception->getMessage());
            $this->fail($invoiceId, $generation, 'This invoice could not be read.');

            return;
        }

        if (! $response instanceof StructuredAgentResponse) {
            $this->recorder->fail($run, 'The invoice extraction did not return fields.');
            $this->fail($invoiceId, $generation, 'The proposed invoice fields could not be used.');

            return;
        }

        $structured = $response->structured;
        $parsed = $this->parse($structured);
        $this->recorder->complete($run, $response, $structured);

        if ($parsed === null) {
            $this->fail($invoiceId, $generation, 'The proposed invoice fields could not be used.');

            return;
        }

        $this->store($invoiceId, $generation, $parsed);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     supplier_name: string,
     *     invoice_number: string,
     *     issued_on: string,
     *     currency: string,
     *     tax_cents: int,
     *     stated_total_cents: int,
     *     confidence: float|null,
     *     lines: list<array{description: string, quantity: int, unit_price_cents: int, stated_line_total_cents: int}>
     * }|null
     */
    private function parse(array $data): ?array
    {
        $supplier = is_string($data['supplier_name'] ?? null) ? trim($data['supplier_name']) : '';
        $number = is_string($data['invoice_number'] ?? null) ? trim($data['invoice_number']) : '';
        $issuedOn = $this->date($data['issued_on'] ?? null);
        $currency = is_string($data['currency'] ?? null) ? strtoupper(trim($data['currency'])) : '';
        $tax = $this->checks->cents($data['tax_cents'] ?? null);
        $total = $this->checks->cents($data['total_cents'] ?? null);
        $lines = $this->lines($data['lines'] ?? null);

        if ($supplier === '' || mb_strlen($supplier) > 200 || $number === '' || mb_strlen($number) > 80) {
            return null;
        }

        if ($issuedOn === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1 || $tax === null || $total === null || $lines === null) {
            return null;
        }

        return [
            'supplier_name' => $supplier,
            'invoice_number' => $number,
            'issued_on' => $issuedOn,
            'currency' => $currency,
            'tax_cents' => $tax,
            'stated_total_cents' => $total,
            'confidence' => $this->confidence($data['confidence'] ?? null),
            'lines' => $lines,
        ];
    }

    /**
     * @return list<array{description: string, quantity: int, unit_price_cents: int, stated_line_total_cents: int}>|null
     */
    private function lines(mixed $rows): ?array
    {
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $lines = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                return null;
            }

            $description = is_string($row['description'] ?? null) ? trim($row['description']) : '';
            $quantity = $this->checks->cents($row['quantity'] ?? null, 1_000_000);
            $unitPrice = $this->checks->cents($row['unit_price_cents'] ?? null);
            $stated = $this->checks->cents($row['line_total_cents'] ?? null);

            if ($description === '' || mb_strlen($description) > 300 || $quantity === null || $unitPrice === null || $stated === null) {
                return null;
            }

            $lines[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPrice,
                'stated_line_total_cents' => $stated,
            ];
        }

        return $lines;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function confidence(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $confidence = (float) $value;

        if ($confidence < 0 || $confidence > 1) {
            return null;
        }

        return round($confidence, 4);
    }

    /**
     * @param  array{
     *     supplier_name: string,
     *     invoice_number: string,
     *     issued_on: string,
     *     currency: string,
     *     tax_cents: int,
     *     stated_total_cents: int,
     *     confidence: float|null,
     *     lines: list<array{description: string, quantity: int, unit_price_cents: int, stated_line_total_cents: int}>
     * }  $parsed
     */
    private function store(string $invoiceId, string $generation, array $parsed): void
    {
        DB::transaction(function () use ($invoiceId, $generation, $parsed): void {
            $invoice = SupplierInvoice::query()->lockForUpdate()->find($invoiceId);

            if ($invoice === null || $invoice->extraction_generation !== $generation) {
                return;
            }

            if ($invoice->status !== SupplierInvoiceStatus::Processing || ! $invoice->isOpen()) {
                return;
            }

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::NeedsReview,
                'failure_reason' => null,
                'supplier_name' => $parsed['supplier_name'],
                'invoice_number' => $parsed['invoice_number'],
                'issued_on' => $parsed['issued_on'],
                'currency' => $parsed['currency'],
                'tax_cents' => $parsed['tax_cents'],
                'stated_total_cents' => $parsed['stated_total_cents'],
                'confidence' => $parsed['confidence'],
            ])->save();

            $invoice->lines()->delete();

            foreach ($parsed['lines'] as $index => $line) {
                $invoice->lines()->create([
                    'position' => $index + 1,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price_cents' => $line['unit_price_cents'],
                    'stated_line_total_cents' => $line['stated_line_total_cents'],
                ]);
            }
        });
    }

    private function current(string $invoiceId, string $generation): ?SupplierInvoice
    {
        $invoice = SupplierInvoice::query()->find($invoiceId);

        if ($invoice === null || $invoice->extraction_generation !== $generation) {
            return null;
        }

        if ($invoice->status !== SupplierInvoiceStatus::Processing || ! $invoice->isOpen()) {
            return null;
        }

        return $invoice;
    }

    private function fail(string $invoiceId, string $generation, string $reason): void
    {
        $invoice = SupplierInvoice::query()->find($invoiceId);

        if ($invoice === null || $invoice->extraction_generation !== $generation) {
            return;
        }

        if ($invoice->status !== SupplierInvoiceStatus::Processing) {
            return;
        }

        $invoice->forceFill([
            'status' => SupplierInvoiceStatus::Failed,
            'failure_reason' => Str::limit($reason, 240),
        ])->save();
    }
}
