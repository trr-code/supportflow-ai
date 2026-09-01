<?php

use App\Enums\TicketCategory;
use App\Livewire\Chat\Widget;
use App\Livewire\Pages\KnowledgeIndex;
use App\Livewire\Pages\KnowledgeShow;
use App\Models\KnowledgeArticle;
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

    $this->get(route('knowledge.index'))
        ->assertOk()
        ->assertSee('Harbor Outfitters policies')
        ->assertSee('These are the seeded articles retrieval uses.')
        ->assertSee('Return window')
        ->assertSee('Window')
        ->assertDontSee('Internal draft');

    Livewire::test(KnowledgeIndex::class)
        ->assertSeeHtml('wire:key="kb-article-'.$article->id.'"')
        ->call('filterCategory', 'returns')
        ->assertSee('Return window')
        ->call('filterCategory', 'billing')
        ->assertDontSee('Return window')
        ->call('askAssistant')
        ->assertDispatched('demo-open-chat');
});

test('knowledge show renders a published seeded article', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Store pickup',
        'slug' => 'store-pickup-browser',
        'category' => TicketCategory::Shipping,
        'body' => "Seattle Flagship can hold replacement parts.\n## Same-day\nAsk before 2pm local time.",
        'is_published' => true,
        'is_seeded' => true,
    ]);

    $this->get(route('knowledge.show', $article->slug))
        ->assertOk()
        ->assertSee('Store pickup')
        ->assertSee('Seattle Flagship can hold replacement parts')
        ->assertSee('Same-day')
        ->assertSee('Back to all policies')
        ->assertSee('Ask the assistant');

    Livewire::test(KnowledgeShow::class, ['slug' => $article->slug])
        ->call('askAssistant')
        ->assertDispatched('demo-open-chat');
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
