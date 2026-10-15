<?php

namespace App\Services;

use App\Models\SupplierInvoice;

class SupplierInvoicePurgeService
{
    public function purgeExpired(): int
    {
        $count = 0;

        SupplierInvoice::query()
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (SupplierInvoice $invoice) use (&$count): void {
                $invoice->delete();
                $count++;
            });

        return $count;
    }
}
