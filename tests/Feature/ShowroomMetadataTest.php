<?php

use App\Models\Ticket;
use App\Models\User;

test('page routes have one primary heading and the intended indexing boundary', function (string $name, bool $private) {
    if ($private && $name !== 'workspaces.preview' && $name !== 'documents.invoices' && $name !== 'tickets.status') {
        $this->actingAs(User::factory()->create());
    }
    $parameters = match ($name) {
        'tickets.status' => ['publicToken' => Ticket::factory()->create()->public_token],
        'agent.tickets.show' => ['ticket' => Ticket::factory()->create()],
        default => [],
    };
    $response = $this->get(route($name, $parameters))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//h1')->length)->toBe(1);
    expect(trim($xpath->query('//title')->item(0)->textContent))->toContain('Lucid');
    expect($xpath->query('//meta[@name="robots" and @content="noindex, nofollow"]')->length)->toBe($private ? 1 : 0);
    if (in_array($name, ['home', 'demo.harbor', 'demo.documents', 'demo.invoices', 'knowledge.index'], true)) {
        expect($xpath->query('//meta[@name="description"]')->length)->toBe(1);
        expect($xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'))->toBe(route($name));
    }
})->with([
    ['home', false], ['demo.harbor', false], ['demo.documents', false], ['demo.invoices', false],
    ['knowledge.index', false], ['demo.workflow', false], ['demo.safety', false],
    ['demo.environment', false], ['workspaces.preview', true], ['documents.invoices', true],
    ['dashboard', true], ['agent.tickets.index', true], ['agent.tickets.show', true],
    ['tickets.status', true], ['profile.edit', true],
]);
