<?php

namespace App\Services;

use App\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierInvoiceAccess
{
    public const string COOKIE = 'supportflow_invoice';

    public function token(Request $request): string
    {
        $existing = $request->cookie(self::COOKIE);

        if (is_string($existing) && strlen($existing) === 64) {
            return $existing;
        }

        $token = Str::random(64);
        $minutes = max(1, (int) config('supportflow.invoices.lifetime_days', 7)) * 24 * 60;

        cookie()->queue(cookie(
            self::COOKIE,
            $token,
            $minutes,
            httpOnly: true,
            sameSite: 'lax',
        ));

        $request->cookies->set(self::COOKIE, $token);

        return $token;
    }

    public function find(Request $request, string $invoiceId): ?SupplierInvoice
    {
        $invoice = $this->owned($request, $invoiceId);

        if ($invoice === null || ! $invoice->isOpen()) {
            return null;
        }

        return $invoice->load('lines');
    }

    public function latest(Request $request): ?SupplierInvoice
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || strlen($token) !== 64) {
            return null;
        }

        $invoice = SupplierInvoice::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        return $invoice?->load('lines');
    }

    private function owned(Request $request, string $invoiceId): ?SupplierInvoice
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || strlen($token) !== 64) {
            return null;
        }

        return SupplierInvoice::query()
            ->whereKey($invoiceId)
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }
}
