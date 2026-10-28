<?php

use App\Ai\Agents\SupplierInvoiceExtractionAgent;
use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Jobs\ExtractSupplierInvoice;
use App\Livewire\Pages\SupplierInvoicePage;
use App\Models\AiRun;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceAccess;
use App\Support\InvoiceMoney;
use App\Support\WorkspaceCopy;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('mismatched model totals stay invalid until corrected and the export matches the review', function () {
    $token = str_repeat('a', 64);

    SupplierInvoiceExtractionAgent::fake([[
        'supplier_name' => 'Northwind Supply',
        'invoice_number' => 'INV-1001',
        'issued_on' => '2026-03-01',
        'currency' => 'USD',
        'tax_cents' => 200,
        'total_cents' => 9999,
        'confidence' => 0.1,
        'totals_match' => true,
        'lines' => [[
            'description' => 'Wool socks',
            'quantity' => 2,
            'unit_price_cents' => 1500,
            'line_total_cents' => 1500,
        ]],
    ]])->preventStrayPrompts();

    $page = Livewire::withCookies([SupplierInvoiceAccess::COOKIE => $token])
        ->test(SupplierInvoicePage::class)
        ->set('document', invoicePdf())
        ->call('store')
        ->assertSee('PDF with selectable text')
        ->assertSet('lines.0.unit_price', '$15.00')
        ->assertSet('lines.0.stated_line_total', '$15.00')
        ->assertSet('tax', '$2.00')
        ->assertSet('statedTotal', '$99.99')
        ->assertSee('Calculated total: $32.00. The invoice total you enter is the one that is saved.')
        ->assertSee('Line 1 does not match quantity times the unit price.')
        ->assertSee('The line totals plus tax do not match the invoice total.')
        ->assertSee('AI extraction confidence: 10%.')
        ->assertSee('Review the fields before confirming; this score does not guarantee accuracy.')
        ->assertDontSee('text-layer')
        ->assertDontSee('private document chat')
        ->call('confirm')
        ->assertNotDispatched('toast-show')
        ->assertSee('Line 1 does not match quantity times the unit price.');

    $invoice = SupplierInvoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::NeedsReview)
        ->and(AiRun::query()->where('feature', AiRunFeature::InvoiceExtraction)->where('status', AiRunStatus::Completed)->exists())->toBeTrue()
        ->and(AiRun::query()->where('supplier_invoice_id', $invoice->id)->sole()->payload)->toBeNull()
        ->and(KnowledgeArticle::query()->count())->toBe(0)
        ->and(KnowledgeChunk::query()->count())->toBe(0);

    $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.json', $invoice))
        ->assertForbidden();

    $page->set('lines.0.stated_line_total', '$30.00')
        ->set('statedTotal', '32.00')
        ->call('save')
        ->assertNotDispatched('toast-show')
        ->assertDontSee('Line 1 does not match quantity times the unit price.')
        ->call('confirm')
        ->assertNotDispatched('toast-show')
        ->assertSee('Confirmed invoice')
        ->assertSee('AI extraction confidence: 10%.')
        ->assertSee("This score is the model's estimate from extraction and does not guarantee accuracy.", false)
        ->assertDontSee('Review the fields before confirming')
        ->assertSeeHtml('class="policy-link"');

    $invoice->refresh();

    expect($invoice->status)->toBe(SupplierInvoiceStatus::Confirmed)
        ->and($invoice->stated_total_cents)->toBe(3200)
        ->and($invoice->lines()->first()?->stated_line_total_cents)->toBe(3000);

    $json = $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.json', $invoice))
        ->assertOk()
        ->streamedContent();
    $csv = $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.csv', $invoice))
        ->assertOk()
        ->streamedContent();

    expect($json)->toContain('Northwind Supply')
        ->toContain('"stated_total_cents": 3200')
        ->toContain('"unit_price_cents": 1500')
        ->not->toContain('9999')
        ->not->toContain('32.00')
        ->not->toContain('$')
        ->and($csv)->toContain('Supplier')
        ->toContain('Invoice number')
        ->toContain('Currency')
        ->toContain('Invoice total')
        ->toContain('Line description')
        ->toContain('Unit price')
        ->toContain('Line total')
        ->toContain('Northwind Supply')
        ->toContain('USD,2.00,32.00')
        ->toContain('15.00')
        ->toContain('30.00')
        ->not->toContain('tax_cents')
        ->not->toContain('3200')
        ->not->toContain('9999')
        ->not->toContain('$');
});

test('a confirmed csv uses decimal amounts and json keeps cents', function () {
    $token = str_repeat('h', 64);
    $invoice = SupplierInvoice::factory()->confirmed()->create([
        'token_hash' => hash('sha256', $token),
        'currency' => 'EUR',
        'tax_cents' => 10,
        'stated_total_cents' => 1999,
    ]);
    $invoice->lines()->create([
        'position' => 1,
        'description' => 'Wool socks',
        'quantity' => 1,
        'unit_price_cents' => 1989,
        'stated_line_total_cents' => 1989,
    ]);

    $csv = $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.csv', $invoice))
        ->assertOk()
        ->streamedContent();
    $json = $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.json', $invoice))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('EUR,0.10,19.99')
        ->toContain('19.89')
        ->not->toContain('tax_cents')
        ->not->toContain('1999')
        ->not->toContain('$')
        ->and($json)->toContain('"currency": "EUR"')
        ->toContain('"tax_cents": 10')
        ->toContain('"stated_total_cents": 1999')
        ->toContain('"unit_price_cents": 1989')
        ->not->toContain('19.99')
        ->not->toContain('0.10');

    $this->withCookie(SupplierInvoiceAccess::COOKIE, str_repeat('i', 64))
        ->get(route('documents.invoices.export.csv', $invoice))
        ->assertForbidden();
    $this->withCookie(SupplierInvoiceAccess::COOKIE, str_repeat('i', 64))
        ->get(route('documents.invoices.export.json', $invoice))
        ->assertForbidden();
});

test('the first upload stays visible before the browser sends the cookie back', function () {
    SupplierInvoiceExtractionAgent::fake([[
        'supplier_name' => 'Northwind Supply',
        'invoice_number' => 'INV-1001',
        'issued_on' => '2026-03-01',
        'currency' => 'USD',
        'tax_cents' => 200,
        'total_cents' => 9999,
        'confidence' => 0.1,
        'lines' => [[
            'description' => 'Wool socks',
            'quantity' => 2,
            'unit_price_cents' => 1500,
            'line_total_cents' => 1500,
        ]],
    ]])->preventStrayPrompts();

    Livewire::test(SupplierInvoicePage::class)
        ->assertSee('Choose file')
        ->assertSee('PDF with selectable text')
        ->assertSee('does not share that content for model training')
        ->assertSeeHtml('data-invoice-file')
        ->assertDontSee('text-layer')
        ->set('document', invoicePdf())
        ->call('store')
        ->assertNotDispatched('toast-show')
        ->assertSee('Line 1 does not match quantity times the unit price.');
});

test('currency text converts to cents without rounding', function (mixed $input, ?int $cents) {
    expect(InvoiceMoney::toCents($input))->toBe($cents);
})->with([
    'dollar sign' => ['$32.00', 3200],
    'plain decimal' => ['32.00', 3200],
    'whole dollars' => ['32', 3200],
    'whole dollars as an integer' => [32, 3200],
    'one decimal place' => ['32.5', 3250],
    'ten cents' => ['0.10', 10],
    'twenty nine cents' => ['0.29', 29],
    'nineteen ninety nine' => ['19.99', 1999],
    'thousands separator' => ['$1,234.56', 123456],
    'maximum amount' => ['1000000.00', 100_000_000],
    'above the maximum' => ['1000000.01', null],
    'extra precision' => ['32.555', null],
    'negative' => ['-1.00', null],
    'blank' => ['', null],
    'float is rejected' => [19.99, null],
]);

test('usd amounts display with a dollar sign and two decimals', function () {
    expect(InvoiceMoney::format(3200, 'USD'))->toBe('$32.00')
        ->and(InvoiceMoney::format(10, 'usd'))->toBe('$0.10')
        ->and(InvoiceMoney::format(0, 'USD'))->toBe('$0.00')
        ->and(InvoiceMoney::format(1999, 'USD'))->toBe('$19.99')
        ->and(InvoiceMoney::format(3200, 'EUR'))->toBe('32.00')
        ->and(InvoiceMoney::decimal(1999))->toBe('19.99')
        ->and(InvoiceMoney::decimal(10))->toBe('0.10')
        ->and(InvoiceMoney::decimal(3200))->toBe('32.00')
        ->and(InvoiceMoney::toCents(InvoiceMoney::format(1999, 'USD')))->toBe(1999)
        ->and(InvoiceMoney::toCents(InvoiceMoney::format(10, 'USD')))->toBe(10);
});

test('a precise decimal saves as cents and invoice actions do not toast', function () {
    $token = str_repeat('g', 64);

    SupplierInvoiceExtractionAgent::fake([[
        'supplier_name' => 'Northwind Supply',
        'invoice_number' => 'INV-1999',
        'issued_on' => '2026-03-01',
        'currency' => 'USD',
        'tax_cents' => 0,
        'total_cents' => 1999,
        'confidence' => 0.4,
        'lines' => [[
            'description' => 'Wool socks',
            'quantity' => 1,
            'unit_price_cents' => 1999,
            'line_total_cents' => 1999,
        ]],
    ]])->preventStrayPrompts();

    $page = Livewire::withCookies([SupplierInvoiceAccess::COOKIE => $token])
        ->test(SupplierInvoicePage::class)
        ->set('document', invoicePdf())
        ->call('store')
        ->assertSet('lines.0.unit_price', '$19.99')
        ->assertSet('statedTotal', '$19.99')
        ->assertSee('Calculated total: $19.99. The invoice total you enter is the one that is saved.')
        ->assertNotDispatched('toast-show')
        ->call('refreshInvoice')
        ->assertNotDispatched('toast-show')
        ->call('refreshInvoice')
        ->assertNotDispatched('toast-show')
        ->set('statedTotal', '19.999')
        ->call('save')
        ->assertHasErrors(['statedTotal'])
        ->assertSee('Enter an amount such as 32.00.')
        ->assertNotDispatched('toast-show')
        ->assertDontSee('Confirmed invoice');

    $invoice = SupplierInvoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::NeedsReview)
        ->and($invoice->stated_total_cents)->toBe(1999);

    $page->set('statedTotal', '$19.99')
        ->call('confirm')
        ->assertNotDispatched('toast-show')
        ->assertSee('Confirmed invoice')
        ->call('refreshInvoice')
        ->assertNotDispatched('toast-show');

    $invoice->refresh();

    expect($invoice->status)->toBe(SupplierInvoiceStatus::Confirmed)
        ->and($invoice->stated_total_cents)->toBe(1999)
        ->and($invoice->tax_cents)->toBe(0)
        ->and($invoice->lines()->first()?->unit_price_cents)->toBe(1999);

    $json = $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.json', $invoice))
        ->assertOk()
        ->streamedContent();

    expect($json)->toContain('"stated_total_cents": 1999')
        ->toContain('"unit_price_cents": 1999')
        ->not->toContain('19.99')
        ->not->toContain('2000')
        ->not->toContain('$');
});

test('a crashed extraction cannot be exported', function () {
    $token = str_repeat('b', 64);

    SupplierInvoiceExtractionAgent::fake(fn (): never => throw new RuntimeException('The model stopped.'));

    Livewire::withCookies([SupplierInvoiceAccess::COOKIE => $token])
        ->test(SupplierInvoicePage::class)
        ->set('document', invoicePdf())
        ->call('store')
        ->assertSee('This invoice could not be read.');

    $invoice = SupplierInvoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::Failed)
        ->and(AiRun::query()->where('feature', AiRunFeature::InvoiceExtraction)->where('status', AiRunStatus::Failed)->exists())->toBeTrue();

    $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.csv', $invoice))
        ->assertForbidden();
});

test('a no-text pdf fails before a model call', function () {
    $token = str_repeat('c', 64);
    SupplierInvoiceExtractionAgent::fake()->preventStrayPrompts();

    Livewire::withCookies([SupplierInvoiceAccess::COOKIE => $token])
        ->test(SupplierInvoicePage::class)
        ->set('document', UploadedFile::fake()->createWithContent(
            'empty.pdf',
            (string) file_get_contents(base_path('tests/Fixtures/empty.pdf')),
        ))
        ->call('store')
        ->assertSee(WorkspaceCopy::NO_TEXT);

    SupplierInvoiceExtractionAgent::assertNeverPrompted();

    $invoice = SupplierInvoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::Failed)
        ->and(AiRun::query()->where('feature', AiRunFeature::InvoiceExtraction)->count())->toBe(0);

    $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.json', $invoice))
        ->assertForbidden();
});

test('another browser cannot open the invoice', function () {
    $token = str_repeat('d', 64);
    $invoice = SupplierInvoice::factory()->create([
        'token_hash' => hash('sha256', $token),
    ]);

    Livewire::withCookies([SupplierInvoiceAccess::COOKIE => str_repeat('e', 64)])
        ->test(SupplierInvoicePage::class)
        ->set('invoiceId', $invoice->id)
        ->assertDontSee('Northwind Supply')
        ->assertDontSee('Ask Harbor & Co');

    $this->withCookie(SupplierInvoiceAccess::COOKIE, str_repeat('e', 64))
        ->get(route('documents.invoices.export.json', $invoice))
        ->assertForbidden();
});

test('an expired invoice cannot be read or exported while its row remains', function () {
    $token = str_repeat('f', 64);
    $invoice = SupplierInvoice::factory()->confirmed()->create([
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinute(),
    ]);
    $invoice->lines()->create([
        'position' => 1,
        'description' => 'Wool socks',
        'quantity' => 2,
        'unit_price_cents' => 1500,
        'stated_line_total_cents' => 3000,
    ]);

    Livewire::withCookies([SupplierInvoiceAccess::COOKIE => $token])
        ->test(SupplierInvoicePage::class)
        ->assertDontSee('Northwind Supply');

    $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route('documents.invoices.export.csv', $invoice))
        ->assertForbidden();

    expect(SupplierInvoice::query()->find($invoice->id))->not->toBeNull();
});

test('negative amounts cannot be stored', function () {
    expect(function () {
        DB::transaction(function () {
            SupplierInvoice::factory()->create(['tax_cents' => -1]);
        });
    })->toThrow(QueryException::class);

    $invoice = SupplierInvoice::factory()->create();

    expect(function () use ($invoice) {
        DB::transaction(function () use ($invoice) {
            $invoice->lines()->create([
                'position' => 1,
                'description' => 'Wool socks',
                'quantity' => 1,
                'unit_price_cents' => -1,
                'stated_line_total_cents' => 0,
            ]);
        });
    })->toThrow(QueryException::class);
});

test('a late failure does not confirm or replace a review', function () {
    $invoice = SupplierInvoice::factory()->create();
    $job = new ExtractSupplierInvoice($invoice->id, $invoice->extraction_generation);

    $job->failed(new RuntimeException('The worker stopped.'));

    expect($invoice->fresh()?->status)->toBe(SupplierInvoiceStatus::NeedsReview);
});

test('the scheduled purge deletes only expired invoices', function () {
    Storage::disk('local')->put('supplier-invoices/old.pdf', 'old');
    Storage::disk('local')->put('supplier-invoices/open.pdf', 'open');

    $expired = SupplierInvoice::factory()->create([
        'storage_path' => 'supplier-invoices/old.pdf',
        'expires_at' => now()->subDay(),
    ]);
    $expired->lines()->create([
        'position' => 1,
        'description' => 'Wool socks',
        'quantity' => 1,
        'unit_price_cents' => 100,
        'stated_line_total_cents' => 100,
    ]);
    $open = SupplierInvoice::factory()->create([
        'storage_path' => 'supplier-invoices/open.pdf',
    ]);

    $this->artisan('invoices:purge-expired')->assertSuccessful();

    expect(SupplierInvoice::query()->find($expired->id))->toBeNull()
        ->and(SupplierInvoice::query()->find($open->id))->not->toBeNull();

    Storage::disk('local')->assertMissing('supplier-invoices/old.pdf');
    Storage::disk('local')->assertExists('supplier-invoices/open.pdf');
});

function invoicePdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'supplier-invoice.pdf',
        (string) file_get_contents(base_path('tests/Fixtures/supplier-invoice.pdf')),
    );
}
