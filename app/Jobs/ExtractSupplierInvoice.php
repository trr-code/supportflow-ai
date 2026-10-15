<?php

namespace App\Jobs;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExtractSupplierInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $invoiceId,
        public string $generation,
    ) {
        $this->onQueue('ai');
    }

    public function handle(SupplierInvoiceExtractor $extractor): void
    {
        $extractor->extract($this->invoiceId, $this->generation);
    }

    public function failed(?Throwable $exception): void
    {
        $invoice = SupplierInvoice::query()->find($this->invoiceId);

        if ($invoice === null || $invoice->extraction_generation !== $this->generation) {
            return;
        }

        if ($invoice->status !== SupplierInvoiceStatus::Processing) {
            return;
        }

        $invoice->forceFill([
            'status' => SupplierInvoiceStatus::Failed,
            'failure_reason' => 'This invoice could not be read.',
        ])->save();
    }
}
