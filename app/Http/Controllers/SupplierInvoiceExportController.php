<?php

namespace App\Http\Controllers;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceAccess;
use App\Services\SupplierInvoiceExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierInvoiceExportController extends Controller
{
    public function csv(Request $request, string $invoice, SupplierInvoiceAccess $access, SupplierInvoiceExport $export): StreamedResponse
    {
        $row = $this->confirmed($request, $invoice, $access);

        return response()->streamDownload(function () use ($export, $row): void {
            echo $export->csv($row);
        }, $export->filename($row, 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function json(Request $request, string $invoice, SupplierInvoiceAccess $access, SupplierInvoiceExport $export): StreamedResponse
    {
        $row = $this->confirmed($request, $invoice, $access);
        $payload = json_encode($export->payload($row), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($payload): void {
            echo $payload === false ? '{}' : $payload;
        }, $export->filename($row, 'json'), [
            'Content-Type' => 'application/json',
        ]);
    }

    private function confirmed(Request $request, string $invoice, SupplierInvoiceAccess $access): SupplierInvoice
    {
        $row = $access->find($request, $invoice);

        if ($row === null || $row->status !== SupplierInvoiceStatus::Confirmed) {
            abort(403);
        }

        return $row;
    }
}
