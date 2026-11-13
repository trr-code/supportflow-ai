<?php

use App\Enums\SupplierInvoiceStatus;
use App\Enums\TicketStatus;
use App\Models\KnowledgeArticle;
use App\Models\SupplierInvoice;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SupplierInvoiceAccess;
use Illuminate\Support\Facades\Blade;

test('HTML screens expose one descriptive main heading', function (string $screen) {
    fakeSupportAi();
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create();
    $article = KnowledgeArticle::query()->create(['title' => 'Return policy', 'slug' => 'heading-return-policy', 'body' => 'Unused returns within thirty days.', 'category' => 'returns', 'is_published' => true, 'is_seeded' => true]);
    $url = match ($screen) {
        'ticket' => route('agent.tickets.show', $ticket),
        'status' => route('tickets.status', $ticket->public_token),
        'policy' => route('knowledge.show', $article->slug),
        default => route($screen),
    };
    $html = $this->get($url)->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $headings = $document->getElementsByTagName('h1');
    expect($headings->length)->toBe(1, $screen)
        ->and(trim($headings->item(0)->textContent))->not->toBe('');
    foreach ((new DOMXPath($document))->query('//dialog//*[@data-flux-heading]') as $heading) {
        expect($heading->tagName)->toBe('h2', $screen);
    }
})->with(['home', 'dashboard', 'agent.tickets.index', 'ticket', 'status', 'policy', 'knowledge.index', 'demo.harbor', 'demo.documents', 'demo.invoices', 'demo.environment', 'demo.safety', 'demo.workflow', 'documents.invoices', 'workspaces.preview', 'profile.edit']);

test('ticket headings remain structured through every processing outcome', function (TicketStatus $status, bool $embedded) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => $status]);
    if ($embedded) {
        $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    }
    $html = $this->get($embedded ? route('demo.harbor') : route('agent.tickets.show', $ticket))->assertOk()->getContent();
    expect(preg_match_all('/<h1\b/i', $html))->toBe(1);
    expect($html)->toContain($embedded ? 'Track your ticket and reply' : 'id="ticket-subject"');
})->with(TicketStatus::cases())->with([false, true]);

test('invoice outcome headings retain one main title and the shared content role', function (SupplierInvoiceStatus $status, bool $embedded) {
    $token = str_repeat('h', 64);
    SupplierInvoice::factory()->create(['token_hash' => hash('sha256', $token), 'status' => $status]);
    $html = $this->withCookie(SupplierInvoiceAccess::COOKIE, $token)
        ->get(route($embedded ? 'demo.invoices' : 'documents.invoices'))->assertOk()->getContent();
    expect(preg_match_all('/<h1\b/i', $html))->toBe(1);
    if (in_array($status, [SupplierInvoiceStatus::NeedsReview, SupplierInvoiceStatus::Confirmed], true)) {
        expect($html)->toContain('lucid-content-heading');
    }
})->with(SupplierInvoiceStatus::cases())->with([false, true]);

test('the reusable authentication header is a real main heading', function () {
    $html = Blade::render('<x-auth-header title="Sign in" description="Access your workspace" />');
    expect($html)->toMatch('/<h1\b[^>]*[^<]*>Sign in<\/h1>/');
});
