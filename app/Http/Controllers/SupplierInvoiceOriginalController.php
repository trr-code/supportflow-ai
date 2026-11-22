<?php

namespace App\Http\Controllers;

use App\Enums\SupplierInvoiceStatus;
use App\Services\SupplierInvoiceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupplierInvoiceOriginalController extends Controller
{
    public function __invoke(Request $request, string $invoice, SupplierInvoiceAccess $access): BinaryFileResponse
    {
        $row = $access->findOpen($request, $invoice);
        abort_unless($row !== null && in_array($row->status, [SupplierInvoiceStatus::NeedsReview, SupplierInvoiceStatus::Confirmed], true), 404);

        $disk = Storage::disk('local');
        $path = 'supplier-invoices/'.$row->id.'.pdf';
        abort_unless($row->storage_path === $path && $disk->exists($path), 404);

        $response = response()->file($disk->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
