<?php

use App\Enums\SuggestedReplyStatus;
use App\Livewire\Pages\TicketShow;
use App\Models\Ticket;
use App\Models\User;
use App\Support\SuggestedReplyCopy;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\TicketSeeder;
use Database\Seeders\UserSeeder;
use Livewire\Livewire;

test('suggested reply copy puts one greeting and the professional closing on their own lines', function () {
    $closing = SuggestedReplyCopy::closing('Alex Rivera');
    $formatted = SuggestedReplyCopy::format(
        'Hi Jamie—unused Harbor Trail Packs can be returned within 30 days.',
        'Jamie Cole',
        'Alex Rivera',
    );

    expect($formatted)->toBe(
        "Hi Jamie,\n\nunused Harbor Trail Packs can be returned within 30 days.\n\n{$closing}"
    )
        ->and($formatted)->not->toContain('Hi Jamie—')
        ->and($formatted)->not->toContain('Hi Jamie —')
        ->and($formatted)->not->toContain('—Alex at Harbor & Co')
        ->and($formatted)->not->toContain('— Alex');

    expect(SuggestedReplyCopy::format(
        "Hi Jamie — unused items.\n\n— Alex at Harbor & Co",
        'Jamie Cole',
    ))->toBe("Hi Jamie,\n\nunused items.\n\n{$closing}");

    expect(SuggestedReplyCopy::format(
        "Hi Priya,\n\nHi,\n\nThe gift card is captured first.\n\nBest,\nAlex Rivera\nHarbor & Co Support",
        'Priya Nair',
    ))->toBe("Hi Priya,\n\nThe gift card is captured first.\n\n{$closing}");

    expect(SuggestedReplyCopy::format(
        "The original box is not required.\n\nBest,\nAlex Rivera\nHarbor & Co Support",
        'Maya Chen',
    ))->toBe("Hi Maya,\n\nThe original box is not required.\n\n{$closing}")
        ->and(substr_count(SuggestedReplyCopy::format("Hi Maya,\n\nThe original box is not required.", 'Maya Chen'), 'Hi Maya,'))->toBe(1)
        ->and(substr_count(SuggestedReplyCopy::format("Hi,\n\nThanks for writing.", 'Maya Chen'), 'Hi,'))->toBe(0);
});

test('seeded SF-10482 cites live return-window chunks and uses greeting layout', function () {
    $this->seed([UserSeeder::class, KnowledgeSeeder::class, TicketSeeder::class]);
    $this->seed([TicketSeeder::class]);

    $ticket = Ticket::query()->where('reference', 'SF-10482')->firstOrFail();
    $reply = $ticket->suggestedReplies()->where('status', SuggestedReplyStatus::Pending)->latest('id')->firstOrFail();

    expect($ticket->suggestedReplies()->where('status', SuggestedReplyStatus::Pending)->count())->toBe(1)
        ->and($reply->grounded)->toBeTrue()
        ->and($reply->cited_chunk_ids)->not->toBeEmpty()
        ->and($reply->citedChunks())->toHaveCount(count($reply->cited_chunk_ids))
        ->and($reply->citedChunks()->pluck('article.slug')->unique()->all())->toBe(['return-window'])
        ->and($reply->body)->toContain('Hi Jamie,')
        ->and($reply->body)->toContain("Best,\nAlex Rivera\nHarbor & Co Support")
        ->and($reply->body)->not->toContain('—Alex at Harbor & Co')
        ->and($reply->body)->toContain('not required')
        ->and($reply->body)->not->toContain('Hi Jamie—')
        ->and($reply->body)->not->toContain('Hi Jamie —')
        ->and($reply->body)->not->toContain('— Alex');

    $this->actingAs(User::query()->first());

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertOk()
        ->assertSee('Grounded: yes')
        ->assertSee('Sources')
        ->assertSee('Return window')
        ->assertSee('Box not required')
        ->assertSee('Prepaid labels')
        ->assertSee('High (91%)—model-estimated')
        ->assertSee('(similarity 0.81)—measured')
        ->assertDontSee('%) —')
        ->assertDontSee(') —measured')
        ->assertDontSee('Hi Jamie—')
        ->assertDontSee('— Alex');
});

test('seeded titles and landing copy use closed em dashes', function () {
    $this->seed([UserSeeder::class, KnowledgeSeeder::class, TicketSeeder::class]);

    expect(Ticket::query()->where('reference', 'SF-10493')->value('subject'))
        ->toBe('Warranty claim—snapped trekking pole')
        ->and(file_get_contents(database_path('seeders/TicketSeeder.php')))
        ->not->toContain(' —')
        ->and(file_get_contents(resource_path('views/livewire/pages/welcome.blade.php')))
        ->toContain('live models—not canned scripts')
        ->toContain('See grounded AI answers—then see how a human support agent reviews and sends the reply.')
        ->toContain('confidence—model-estimated')
        ->not->toContain('live models —')
        ->not->toContain('answers — then');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('live models—not canned scripts')
        ->assertDontSee('live models — not canned scripts');
});
