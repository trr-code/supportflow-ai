<?php

namespace App\Livewire\Pages;

use App\Enums\SupplierInvoiceStatus;
use App\Jobs\ExtractSupplierInvoice;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Services\SupplierInvoiceAccess;
use App\Support\InvoiceMoney;
use App\Support\SupplierInvoiceCheck;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class SupplierInvoicePage extends Component
{
    use WithFileUploads;

    public bool $embedded = false;

    public ?string $invoiceId = null;

    public ?TemporaryUploadedFile $document = null;

    public string $supplierName = '';

    public string $invoiceNumber = '';

    public string $issuedOn = '';

    public string $currency = '';

    public string $tax = '0.00';

    public string $statedTotal = '0.00';

    /** @var list<array{id: string, description: string, quantity: int|string, unit_price: string, stated_line_total: string}> */
    public array $lines = [];

    public function mount(SupplierInvoiceAccess $access): void
    {
        $invoice = $access->latest(request());

        if ($invoice !== null) {
            $this->fillFrom($invoice);
        }
    }

    public function store(SupplierInvoiceAccess $access): void
    {
        $maxKilobytes = (int) ceil(((int) config('supportflow.invoices.max_file_bytes', 8 * 1024 * 1024)) / 1024);

        $this->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:'.$maxKilobytes],
        ]);

        $document = $this->document;

        if ($document === null) {
            return;
        }

        $generation = (string) Str::uuid();
        $invoice = SupplierInvoice::query()->create([
            'token_hash' => hash('sha256', $access->token(request())),
            'original_name' => 'invoice.pdf',
            'storage_path' => 'supplier-invoices/pending',
            'byte_size' => 0,
            'status' => SupplierInvoiceStatus::Processing,
            'extraction_generation' => $generation,
            'expires_at' => now()->addDays((int) config('supportflow.invoices.lifetime_days', 7)),
        ]);

        $path = $document->storeAs('supplier-invoices', $invoice->id.'.pdf', 'local');

        if (! is_string($path)) {
            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Failed,
                'failure_reason' => 'This invoice could not be stored.',
            ])->save();
            $this->fillFrom($invoice);
            $this->reset('document');

            return;
        }

        $name = trim($document->getClientOriginalName());
        $invoice->forceFill([
            'original_name' => $name === '' ? 'invoice.pdf' : Str::limit($name, 200, ''),
            'storage_path' => $path,
            'byte_size' => (int) $document->getSize(),
        ])->save();

        ExtractSupplierInvoice::dispatch($invoice->id, $generation);

        $fresh = $invoice->fresh(['lines']);

        if ($fresh instanceof SupplierInvoice) {
            $this->fillFrom($fresh);
        }

        $this->reset('document');
    }

    public function refreshInvoice(SupplierInvoiceAccess $access): void
    {
        if ($this->invoiceId === null) {
            return;
        }

        $invoice = $access->find(request(), $this->invoiceId);

        if ($invoice === null) {
            $this->invoiceId = null;

            return;
        }

        if ($invoice->status !== SupplierInvoiceStatus::Processing) {
            $this->fillFrom($invoice);
        }
    }

    public function save(): void
    {
        $this->persist(false);
    }

    public function confirm(): void
    {
        $this->persist(true);
    }

    public function render(SupplierInvoiceAccess $access, SupplierInvoiceCheck $checks): View
    {
        $invoice = $this->invoiceId === null ? null : $access->find(request(), $this->invoiceId);
        $messages = [];
        $calculatedTotal = null;

        if ($invoice !== null && $invoice->status === SupplierInvoiceStatus::NeedsReview) {
            $messages = $checks->inspect($this->linesInCents(), InvoiceMoney::toCents($this->tax), InvoiceMoney::toCents($this->statedTotal));
            $calculatedCents = $this->calculatedTotalCents();
            $calculatedTotal = $calculatedCents === null ? null : InvoiceMoney::format($calculatedCents, $this->currency);
        }

        return view('livewire.pages.supplier-invoice', [
            'invoice' => $invoice,
            'checks' => $messages,
            'calculatedTotal' => $calculatedTotal,
            'disclosure' => 'The PDF and its text are sent to OpenAI so this page can propose fields. This organization does not share that content for model training. Confirming an invoice stores your corrections here. It does not pay the invoice.',
        ])->layout('components.layouts.workspace', [
            'title' => 'Invoice review',
        ]);
    }

    private function persist(bool $confirm): void
    {
        $invoice = app(SupplierInvoiceAccess::class)->find(request(), (string) $this->invoiceId);

        if ($invoice === null || $invoice->status !== SupplierInvoiceStatus::NeedsReview) {
            return;
        }

        $this->currency = strtoupper(trim($this->currency));

        $this->validate([
            'supplierName' => ['required', 'string', 'max:200'],
            'invoiceNumber' => ['required', 'string', 'max:80'],
            'issuedOn' => ['required', 'date_format:Y-m-d'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'tax' => ['required', $this->amountRule()],
            'statedTotal' => ['required', $this->amountRule()],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:300'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.unit_price' => ['required', $this->amountRule()],
            'lines.*.stated_line_total' => ['required', $this->amountRule()],
        ]);

        $taxCents = InvoiceMoney::toCents($this->tax);
        $statedTotalCents = InvoiceMoney::toCents($this->statedTotal);
        $lines = $this->linesForStorage();

        if ($taxCents === null || $statedTotalCents === null || $lines === null) {
            return;
        }

        DB::transaction(function () use ($invoice, $confirm, $taxCents, $statedTotalCents, $lines): void {
            $locked = SupplierInvoice::query()->lockForUpdate()->find($invoice->id);

            if ($locked === null || $locked->status !== SupplierInvoiceStatus::NeedsReview || ! $locked->isOpen()) {
                return;
            }

            $locked->forceFill([
                'supplier_name' => trim($this->supplierName),
                'invoice_number' => trim($this->invoiceNumber),
                'issued_on' => $this->issuedOn,
                'currency' => $this->currency,
                'tax_cents' => $taxCents,
                'stated_total_cents' => $statedTotalCents,
            ])->save();

            $locked->lines()->delete();

            foreach ($lines as $line) {
                $locked->lines()->create($line);
            }

            $locked->load('lines');
            $messages = app(SupplierInvoiceCheck::class)->inspect(
                array_values($locked->lines->map(fn (SupplierInvoiceLine $line): array => [
                    'quantity' => $line->quantity,
                    'unit_price_cents' => $line->unit_price_cents,
                    'stated_line_total_cents' => $line->stated_line_total_cents,
                ])->all()),
                $locked->tax_cents,
                $locked->stated_total_cents,
            );

            if ($confirm && $messages === []) {
                $locked->forceFill([
                    'status' => SupplierInvoiceStatus::Confirmed,
                    'confirmed_at' => now(),
                ])->save();
            }
        });

        $fresh = app(SupplierInvoiceAccess::class)->find(request(), $invoice->id);

        if ($fresh instanceof SupplierInvoice) {
            $this->fillFrom($fresh);
        }
    }

    private function fillFrom(SupplierInvoice $invoice): void
    {
        $invoice->loadMissing('lines');
        $this->invoiceId = $invoice->id;
        $this->supplierName = (string) $invoice->supplier_name;
        $this->invoiceNumber = (string) $invoice->invoice_number;
        $this->issuedOn = $invoice->issued_on?->toDateString() ?? '';
        $currency = (string) $invoice->currency;
        $this->currency = $currency;
        $this->tax = InvoiceMoney::format((int) ($invoice->tax_cents ?? 0), $currency);
        $this->statedTotal = InvoiceMoney::format((int) ($invoice->stated_total_cents ?? 0), $currency);
        $this->lines = array_values($invoice->lines->map(fn (SupplierInvoiceLine $line): array => [
            'id' => $line->id,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price' => InvoiceMoney::format($line->unit_price_cents, $currency),
            'stated_line_total' => InvoiceMoney::format($line->stated_line_total_cents, $currency),
        ])->all());
    }

    private function amountRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (InvoiceMoney::toCents($value) === null) {
                $fail('Enter an amount such as 32.00.');
            }
        };
    }

    /**
     * @return list<array{quantity: mixed, unit_price_cents: int|null, stated_line_total_cents: int|null}>
     */
    private function linesInCents(): array
    {
        return array_values(array_map(fn (array $line): array => [
            'quantity' => $line['quantity'] ?? null,
            'unit_price_cents' => InvoiceMoney::toCents($line['unit_price'] ?? null),
            'stated_line_total_cents' => InvoiceMoney::toCents($line['stated_line_total'] ?? null),
        ], $this->lines));
    }

    /**
     * @return list<array{position: int, description: string, quantity: int, unit_price_cents: int, stated_line_total_cents: int}>|null
     */
    private function linesForStorage(): ?array
    {
        $stored = [];

        foreach (array_values($this->lines) as $index => $line) {
            $unitPrice = InvoiceMoney::toCents($line['unit_price'] ?? null);
            $statedLine = InvoiceMoney::toCents($line['stated_line_total'] ?? null);

            if ($unitPrice === null || $statedLine === null) {
                return null;
            }

            $stored[] = [
                'position' => $index + 1,
                'description' => trim((string) $line['description']),
                'quantity' => (int) $line['quantity'],
                'unit_price_cents' => $unitPrice,
                'stated_line_total_cents' => $statedLine,
            ];
        }

        return $stored;
    }

    private function calculatedTotalCents(): ?int
    {
        $checks = app(SupplierInvoiceCheck::class);
        $sum = 0;

        foreach ($this->lines as $line) {
            $quantity = $checks->cents($line['quantity'] ?? null, 1_000_000);
            $price = InvoiceMoney::toCents($line['unit_price'] ?? null);

            if ($quantity === null || $price === null) {
                return null;
            }

            $sum += $quantity * $price;
        }

        $tax = InvoiceMoney::toCents($this->tax);

        return $tax === null ? null : $sum + $tax;
    }
}
