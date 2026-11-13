<div class="space-y-6" @if ($invoice?->status === \App\Enums\SupplierInvoiceStatus::Processing) wire:poll.2s="refreshInvoice" @endif>
    @unless ($embedded)
    <section class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">Document processing</p>
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">Supplier invoice</h1>
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600">
            Upload a PDF with selectable text. The assistant reads the invoice and proposes the fields. This page checks the amounts. You review and correct them, confirm the invoice, then download CSV or JSON.
        </p>
    </section>
    @endunless

    @if ($invoice !== null && in_array($invoice->status, [\App\Enums\SupplierInvoiceStatus::NeedsReview, \App\Enums\SupplierInvoiceStatus::Confirmed], true))
        <flux:button type="button" class="showroom-ticket-secondary" wire:click="backToInvoiceChoices">Back to invoice choices</flux:button>
    @endif

    @if ($invoice === null || $invoice->status === \App\Enums\SupplierInvoiceStatus::Failed)
        <form wire:submit="store" class="space-y-4" data-invoice-upload>
            <h2 class="lucid-content-heading text-harbor-ink">Try a sample or upload your own invoice</h2>
            <p class="text-sm text-zinc-500">The sample is fictional. If you upload your own invoice, use a non-sensitive file.</p>

            @if ($invoice?->failure_reason)
                <p class="text-sm leading-relaxed text-red-700" data-invoice-failure role="alert">{{ $invoice->failure_reason }}</p>
            @endif
            <div class="space-y-3">
                <flux:button type="button" class="showroom-ticket-secondary" wire:click="useSample" wire:loading.attr="disabled" wire:target="document,store,useSample">Try a sample invoice</flux:button>
                <p class="text-sm font-medium text-harbor-ink">Upload your own invoice PDF</p>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="file-choose relative inline-flex min-h-10 cursor-pointer items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-800 shadow-xs hover:bg-zinc-50">
                        Choose file
                        <input
                            type="file"
                            wire:model="document"
                            accept=".pdf,application/pdf"
                            data-invoice-file
                            class="absolute inset-0 cursor-pointer opacity-0"
                        />
                    </label>
                    <flux:button type="submit" class="showroom-ticket-primary" wire:key="invoice-extract-{{ $document ? 'selected' : 'empty' }}" :disabled="$document === null" wire:loading.attr="disabled" wire:target="document,store,useSample">Extract fields</flux:button>
                </div>
                <p wire:loading wire:target="document" class="text-sm text-zinc-600">Uploading…</p>
                @if ($document)
                    <p class="text-sm text-harbor-ink">{{ $document->getClientOriginalName() }}</p>
                @endif
            </div>
            @error('document')
                <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
            @enderror
            <p class="text-xs text-zinc-500">Upload one invoice PDF up to {{ (int) (config('supportflow.invoices.max_file_bytes') / 1048576) }} MB. Before uploading, check that the PDF contains the supplier name, invoice number, date, line items, and total. The PDF must have text you can highlight and copy. Image-only scans won’t work.</p>
        </form>
    @endif

    @if ($invoice !== null && $invoice->status === \App\Enums\SupplierInvoiceStatus::Processing)
        <section class="space-y-2" data-invoice-status="processing" role="status">
            <h2 class="lucid-content-heading break-words">{{ $invoice->original_name }}</h2>
            <x-status-badge status="processing" label="Reading the invoice…" />
        </section>
    @endif

    @if ($invoice !== null && in_array($invoice->status, [\App\Enums\SupplierInvoiceStatus::NeedsReview, \App\Enums\SupplierInvoiceStatus::Confirmed], true))
        <form wire:submit="save" class="space-y-5" data-invoice-status="{{ $invoice->status->value }}">
            <div class="space-y-1">
                <h2 class="lucid-content-heading text-harbor-ink">
                    {{ $invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed ? 'Confirmed invoice' : 'Review the proposed fields' }}
                </h2>
                <p class="break-words text-sm text-zinc-600">{{ $invoice->original_name }}</p>
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
                <div class="space-y-1 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-950" data-invoice-checks>
                    <h3 class="font-semibold">Amounts don’t match</h3>
                    <ul class="space-y-1">
                        @foreach ($checks as $check)
                            <li wire:key="invoice-check-{{ hash('sha256', $check) }}">{{ $check }}</li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($invoice->status === \App\Enums\SupplierInvoiceStatus::NeedsReview)
                <x-status-badge status="ready" label="Amounts match" />
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
                <h3 class="lucid-section-heading">Line items</h3>
                @foreach ($lines as $index => $line)
                    <fieldset wire:key="invoice-line-{{ $line['id'] }}" class="space-y-3 border-b border-zinc-200 pb-4">
                        <legend class="text-sm font-medium text-harbor-ink">Line {{ $index + 1 }}</legend>
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
                    <flux:button type="submit" class="showroom-ticket-secondary">Save corrections</flux:button>
                    <flux:button type="button" class="showroom-ticket-primary" wire:click="confirm" :disabled="$checks !== []">Confirm invoice</flux:button>
                </div>
            @else
                <div class="flex flex-wrap gap-4 text-sm">
                    <a href="{{ route('documents.invoices.export.csv', $invoice) }}" class="policy-link">Download CSV</a>
                    <a href="{{ route('documents.invoices.export.json', $invoice) }}" class="policy-link">Download JSON</a>
                </div>
            @endif
        </form>
    @endif

    <details wire:key="invoice-privacy" wire:ignore.self class="showroom-document-settings">
        <summary><span>Files and privacy</span><x-lucid.icon name="chevron" /></summary>
        <div class="showroom-document-option-body"><p class="text-sm leading-relaxed">{{ $disclosure }}</p></div>
    </details>
</div>
