<?php

namespace App\Services;

use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SupplierInvoicePurgeService
{
    public function purgeExpired(): int
    {
        $count = 0;
        $failures = 0;

        SupplierInvoice::query()
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (SupplierInvoice $invoice) use (&$count, &$failures): void {
                try {
                    $invoice->delete();
                    $count++;
                } catch (Throwable $exception) {
                    $failures++;
                    Log::warning('Invoice cleanup failed; it will retry.', ['invoice_id' => $invoice->id, 'exception_type' => $exception::class]);
                }
            });

        if ($failures > 0) {
            throw new RuntimeException("Cleanup could not finish for {$failures} invoices; cleanup will retry.");
        }

        return $count;
    }
}
