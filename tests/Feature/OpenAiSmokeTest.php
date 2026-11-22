<?php

use App\Enums\SupplierInvoiceStatus;
use App\Livewire\Pages\SupplierInvoicePage;
use App\Models\AiRun;
use App\Models\SupplierInvoice;
use App\Models\Ticket;
use App\Services\TicketIntakeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('optional live OpenAI smoke for triage', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Return an unused Harbor Trail Pack',
        'description' => 'The pack is unused with tags. What is the return window?',
    ]);

    app(TicketIntakeService::class)->process($ticket);

    $ticket->refresh();
    expect($ticket->ai_summary)->not->toBeEmpty();
})->group('openai')->skip(fn (): bool => ! filter_var(env('OPENAI_SMOKE'), FILTER_VALIDATE_BOOL));

test('optional live OpenAI Northwind sample extracts the printed mismatch', function () {
    Storage::fake('local');

    Livewire::test(SupplierInvoicePage::class)->call('useSample')->assertHasNoErrors()
        ->assertSee('Amounts don’t match');

    $invoice = SupplierInvoice::query()->sole();
    expect($invoice->status)->toBe(SupplierInvoiceStatus::NeedsReview)
        ->and($invoice->invoice_number)->toBe('INV-1002')
        ->and($invoice->stated_total_cents)->toBe(23745)
        ->and($invoice->tax_cents)->toBe(1685)
        ->and($invoice->lines()->orderBy('position')->pluck('stated_line_total_cents')->all())->toBe([6280, 8950, 2460, 3480]);
})->group('openai')->skip(fn (): bool => ! filter_var(env('OPENAI_SMOKE'), FILTER_VALIDATE_BOOL));

test('optional live OpenAI invoice upload preserves review boundaries and minimizes retained content', function () {
    Storage::fake('local');

    Livewire::test(SupplierInvoicePage::class)
        ->set('document', UploadedFile::fake()->createWithContent(
            'invoice.pdf', file_get_contents(base_path('tests/Fixtures/supplier-invoice.pdf')),
        ))
        ->call('store')
        ->assertHasNoErrors();

    $invoice = SupplierInvoice::query()->sole();
    expect($invoice->status)->toBe(SupplierInvoiceStatus::NeedsReview);
    expect($invoice->supplier_name)->toBe('Northwind Supply');
    expect($invoice->invoice_number)->toBe('INV-1001');
    expect($invoice->stated_total_cents)->toBe(9999);
    expect($invoice->confirmed_at)->toBeNull();
    expect($invoice->lines()->count())->toBeGreaterThan(0);
    $run = AiRun::query()->where('supplier_invoice_id', $invoice->id)->sole();
    expect($run->payload)->toBeNull();
    expect($run->input_tokens)->toBeGreaterThan(0);
})->group('openai')->skip(fn (): bool => ! filter_var(env('OPENAI_SMOKE'), FILTER_VALIDATE_BOOL));
