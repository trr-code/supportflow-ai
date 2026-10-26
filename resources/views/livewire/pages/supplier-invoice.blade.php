<div class="space-y-8" @if ($invoice?->status === \App\Enums\SupplierInvoiceStatus::Processing) wire:poll.2s="refreshInvoice" @endif>
    <section class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">Document processing</p>
        @if ($embedded)
            <h2 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">Supplier invoice</h2>
        @else
            <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">Supplier invoice</h1>
        @endif
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600">
            Upload a PDF with selectable text. The assistant reads the invoice and proposes the fields. This page checks the amounts. You review and correct them, confirm the invoice, then download CSV or JSON.
        </p>
    </section>

    <p class="max-w-3xl rounded-2xl border border-harbor-sand-deep bg-white p-5 text-sm leading-relaxed text-zinc-700">
        {{ $disclosure }}
    </p>

    @if ($invoice === null || $invoice->status === \App\Enums\SupplierInvoiceStatus::Failed)
        <form wire:submit="store" class="max-w-xl space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5">
            <h2 class="text-lg font-semibold text-harbor-ink">Upload a PDF</h2>
            @if ($invoice?->failure_reason)
                <p class="text-sm leading-relaxed text-red-700" data-invoice-failure>{{ $invoice->failure_reason }}</p>
            @endif
            <div class="space-y-2">
                <p class="text-sm font-medium text-harbor-ink">Invoice PDF</p>
                <label class="file-choose relative inline-flex cursor-pointer items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-800 shadow-xs hover:bg-zinc-50">
                    Choose file
                    <input
                        type="file"
                        wire:model="document"
                        accept=".pdf,application/pdf"
                        data-invoice-file
                        class="absolute inset-0 cursor-pointer opacity-0"
                    />
                </label>
                <p wire:loading wire:target="document" class="text-sm text-zinc-600">Uploading…</p>
                @if ($document)
                    <p class="text-sm text-harbor-ink">{{ $document->getClientOriginalName() }}</p>
                @endif
            </div>
            @error('document')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
            <flux:button type="submit" variant="primary">Extract fields</flux:button>
        </form>
    @endif

    @if ($invoice !== null && $invoice->status === \App\Enums\SupplierInvoiceStatus::Processing)
        <p class="text-sm text-zinc-600" data-invoice-status="processing">Reading the invoice…</p>
    @endif

    @if ($invoice !== null && in_array($invoice->status, [\App\Enums\SupplierInvoiceStatus::NeedsReview, \App\Enums\SupplierInvoiceStatus::Confirmed], true))
        <form wire:submit="save" class="max-w-3xl space-y-5 rounded-2xl border border-harbor-sand-deep bg-white p-5" data-invoice-status="{{ $invoice->status->value }}">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold text-harbor-ink">
                    {{ $invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed ? 'Confirmed invoice' : 'Review the proposed fields' }}
                </h2>
                @if ($invoice->confidence !== null)
                    <p class="text-sm text-zinc-600">
                        AI extraction confidence: {{ number_format((float) $invoice->confidence * 100, 0) }}%.
                        @if ($invoice->status === \App\Enums\SupplierInvoiceStatus::NeedsReview)
                            Review the fields before confirming; this score does not guarantee accuracy.
                        @else
                            This score is the model's estimate from extraction and does not guarantee accuracy.
                        @endif
                    </p>
                @endif
            </div>

            @if ($checks !== [])
                <ul class="space-y-1 rounded-xl bg-harbor-sand px-4 py-3 text-sm text-harbor-ink" data-invoice-checks>
                    @foreach ($checks as $check)
                        <li wire:key="invoice-check-{{ hash('sha256', $check) }}">{{ $check }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($calculatedTotal !== null)
                <p class="text-sm text-zinc-600">Calculated total: {{ $calculatedTotal }}. The invoice total you enter is the one that is saved.</p>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="supplierName" label="Supplier" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                <flux:input wire:model="invoiceNumber" label="Invoice number" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                <flux:input wire:model="issuedOn" label="Date" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                <flux:input wire:model="currency" label="Currency" maxlength="3" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                <flux:input wire:model="tax" label="Tax" inputmode="decimal" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                <flux:input wire:model="statedTotal" label="Invoice total" inputmode="decimal" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
            </div>

            <div class="space-y-4">
                @foreach ($lines as $index => $line)
                    <fieldset wire:key="invoice-line-{{ $line['id'] }}" class="space-y-3 rounded-xl border border-harbor-sand-deep p-4">
                        <legend class="px-1 text-sm font-medium text-harbor-ink">Line {{ $index + 1 }}</legend>
                        <flux:input wire:model="lines.{{ $index }}.description" label="Description" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                        <div class="grid gap-3 sm:grid-cols-3">
                            <flux:input wire:model="lines.{{ $index }}.quantity" label="Quantity" inputmode="numeric" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                            <flux:input wire:model="lines.{{ $index }}.unit_price" label="Unit price" inputmode="decimal" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                            <flux:input wire:model="lines.{{ $index }}.stated_line_total" label="Line total" inputmode="decimal" :disabled="$invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed" />
                        </div>
                    </fieldset>
                @endforeach
            </div>

            @if ($invoice->status === \App\Enums\SupplierInvoiceStatus::NeedsReview)
                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit">Save corrections</flux:button>
                    <flux:button type="button" variant="primary" wire:click="confirm" :disabled="$checks !== []">Confirm invoice</flux:button>
                </div>
            @else
                <div class="flex flex-wrap gap-4 text-sm">
                    <a href="{{ route('documents.invoices.export.csv', $invoice) }}" class="policy-link">Download CSV</a>
                    <a href="{{ route('documents.invoices.export.json', $invoice) }}" class="policy-link">Download JSON</a>
                </div>
            @endif
        </form>
    @endif
</div>
