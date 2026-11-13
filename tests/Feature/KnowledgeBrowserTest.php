<?php

use App\Enums\TicketCategory;
use App\Enums\UserRole;
use App\Livewire\Chat\Widget;
use App\Livewire\Pages\KnowledgeIndex;
use App\Livewire\Pages\KnowledgeShow;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Support\PolicyNavigation;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Livewire\Livewire;

test('knowledge index lists published seeded articles by category', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'return-window-browser',
        'category' => TicketCategory::Returns,
        'body' => "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.",
        'is_published' => true,
        'is_seeded' => true,
    ]);

    KnowledgeArticle::query()->create([
        'title' => 'Internal draft',
        'slug' => 'internal-draft',
        'category' => TicketCategory::General,
        'body' => 'Should not appear on the public browser.',
        'is_published' => false,
        'is_seeded' => true,
    ]);

    KnowledgeArticle::query()->create([
        'title' => 'Billing adjustment',
        'slug' => 'billing-adjustment-browser',
        'category' => TicketCategory::Billing,
        'body' => 'Contact support about a billing adjustment.',
        'is_published' => true,
        'is_seeded' => true,
    ]);

    $this->get(route('knowledge.index'))
        ->assertOk()
        ->assertSee('Harbor Outfitters policies')
        ->assertSee('Read the published policies behind Harbor’s answers.')
        ->assertSee('Return window')
        ->assertSee('Window')
        ->assertSee('policy-link', false)
        ->assertDontSee('Internal draft');

    Livewire::test(KnowledgeIndex::class)
        ->assertSeeHtml('wire:key="kb-article-'.$article->id.'"')
        ->call('filterCategory', 'returns')
        ->assertSee('Return window')
        ->assertDontSee('Billing adjustment')
        ->call('filterCategory', 'billing')
        ->assertDontSee('Return window')
        ->assertSee('Billing adjustment')
        ->call('filterCategory', 'unknown-category')
        ->assertSet('category', 'all')
        ->assertSee('Return window')->assertSee('Billing adjustment')
        ->assertDontSee('Internal draft')
        ->assertDontSee('Ask the assistant');
});

test('knowledge show renders a published seeded article', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Store pickup',
        'slug' => 'store-pickup-browser',
        'category' => TicketCategory::Shipping,
        'body' => "Seattle Flagship can hold replacement parts. Email noreply@harborandco.example.\n## Same-day\nAsk before 2pm local time.",
        'is_published' => true,
        'is_seeded' => true,
    ]);

    $this->get(route('knowledge.show', $article->slug))
        ->assertOk()
        ->assertSee('Store pickup')
        ->assertSee('Seattle Flagship can hold replacement parts')
        ->assertSee('Same-day')
        ->assertSee('Back to all policies')
        ->assertDontSee('Ask the assistant')
        ->assertSee('href="mailto:noreply@harborandco.example"', false)
        ->assertSee('policy-link', false)
        ->assertSee('policy-prose', false);

    Livewire::test(KnowledgeShow::class, ['slug' => $article->slug])
        ->assertSee('Fictional demo');
});

test('knowledge show 404s unpublished or unknown slugs', function () {
    KnowledgeArticle::query()->create([
        'title' => 'Hidden',
        'slug' => 'hidden-policy',
        'category' => TicketCategory::General,
        'body' => 'Not for visitors.',
        'is_published' => false,
        'is_seeded' => true,
    ]);

    $this->get(route('knowledge.show', 'hidden-policy'))->assertNotFound();
    $this->get(route('knowledge.show', 'does-not-exist'))->assertNotFound();
});

test('opening chat from the knowledge browser does not send a message', function () {
    Livewire::test(Widget::class)
        ->assertSet('open', false)
        ->call('openChat')
        ->assertSet('open', true)
        ->assertSet('question', '')
        ->assertSet('streaming', false);
});

test('policies keep the authorized ticket origin and selected category', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::DemoAgent]));
    $ticket = Ticket::factory()->create();
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window', 'slug' => 'context-return-window', 'category' => 'returns',
        'body' => 'Return conditions.', 'is_published' => true, 'is_seeded' => true,
    ]);
    $origin = ['from' => 'ticket', 'ticket' => $ticket->id];

    $this->get(route('knowledge.index', ['topic' => 'returns', ...$origin]))
        ->assertOk()->assertSee('Back to ticket')
        ->assertSee('aria-label="Agent desk"', false)
        ->assertSee(route('knowledge.show', ['slug' => $article->slug, 'topic' => 'returns', ...$origin]));

    $this->get(route('knowledge.show', ['slug' => $article->slug, 'topic' => 'returns', ...$origin]))
        ->assertOk()->assertSee('Back to ticket')
        ->assertSee('aria-label="Agent desk"', false)
        ->assertSee('<link rel="canonical" href="'.route('knowledge.show', $article->slug).'">', false)
        ->assertSee(route('agent.tickets.show', $ticket))
        ->assertSee(route('knowledge.index', ['topic' => 'returns', ...$origin]));
});

test('public policy navigation ignores unauthorized tickets and arbitrary return locations', function () {
    $ticket = Ticket::factory()->create();
    $this->get(route('knowledge.index', ['from' => 'ticket', 'ticket' => $ticket->id]))
        ->assertOk()->assertDontSee('Back to ticket')->assertDontSee(route('agent.tickets.show', $ticket))
        ->assertSee('aria-label="Demo navigation"', false);
    $this->get(route('knowledge.index', ['from' => 'https://example.com', 'topic' => ['returns']]))
        ->assertOk()->assertDontSee('https://example.com');
    $this->get(route('knowledge.index', ['from' => 'chat']))
        ->assertOk()->assertSee('Back to customer chat')
        ->assertSee(route('demo.harbor', ['view' => 'chat']));
});

test('policy navigation rejects missing and invalid ticket return identifiers', function () {
    expect(PolicyNavigation::returnUrl(['from' => 'ticket']))->toBeNull()
        ->and(PolicyNavigation::returnUrl(['from' => 'ticket', 'ticket' => 0]))->toBeNull()
        ->and(PolicyNavigation::returnUrl(['from' => 'ticket', 'ticket' => 42]))->toBe(route('agent.tickets.show', 42));
});

test('policy origin accepts only positive ticket route identifiers', function (mixed $value, ?int $expected) {
    $request = Request::create('/agent/tickets/42');
    $route = new Route('GET', 'agent/tickets/{ticket}', fn () => null);
    $route->name('agent.tickets.show')->bind($request);
    $route->setParameter('ticket', $value);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    expect(PolicyNavigation::forCurrentPage())->toBe($expected === null ? [] : ['from' => 'ticket', 'ticket' => $expected]);
})->with([
    [42, 42],
    ['42', 42],
    [null, null],
    ['not-a-ticket', null],
    [-1, null],
    [new stdClass, null],
]);

test('returning from a policy opens chat rather than the previous support review', function () {
    $this->withSession(['supportflow.harbor.center' => 'human']);
    $this->get(route('demo.harbor', ['view' => 'chat']))
        ->assertOk()->assertSee('Harbor support')->assertDontSee('Support agent view')
        ->assertSessionHas('supportflow.harbor.center', 'chat');
});

test('policy descriptions summarize the first paragraph without merging section titles', function (string $body) {
    $article = new KnowledgeArticle(['body' => $body]);
    expect($article->excerpt())->toBe('Unused returns within thirty days.');
})->with([
    "Unused returns within thirty days.\n## Box not required\nA sturdy carton is fine.",
    "## Return window\nUnused returns within thirty days.\n## Box not required\nA sturdy carton is fine.",
]);
