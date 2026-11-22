<?php

use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketShow;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Services\WorkspaceAccess;
use App\Support\SuggestedReplyCopy;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\TicketSeeder;
use Database\Seeders\UserSeeder;
use Livewire\Livewire;

test('the retailer rename changes only the shared seeded catalog', function () {
    $catalog = KnowledgeArticle::create(['title' => 'Catalog', 'slug' => 'rename-catalog', 'category' => 'returns', 'body' => 'Harbor & Co catalog.', 'is_seeded' => true]);
    $custom = KnowledgeArticle::create(['title' => 'Owner document', 'slug' => 'rename-custom', 'category' => 'returns', 'body' => 'Harbor & Co in an owner document.', 'is_seeded' => false]);
    $private = $custom->replicate();
    $private->fill(['slug' => 'rename-private', 'workspace_id' => app(WorkspaceAccess::class)->start()->id, 'is_seeded' => true])->save();
    $migration = require database_path('migrations/2026_11_20_214925_standardize_seeded_harbor_brand.php');
    $migration->up();
    expect($catalog->fresh()->body)->toBe('Harbor Outfitters catalog.')
        ->and($custom->fresh()->body)->toBe('Harbor & Co in an owner document.')
        ->and($private->fresh()->body)->toBe('Harbor & Co in an owner document.');
});

test('placeholder customer names receive a natural greeting and the consistent retailer signature', function () {
    expect(SuggestedReplyCopy::format('Your request is with our team.', 'Demo customer'))
        ->toStartWith('Hi there,')->toEndWith('Harbor Outfitters Support')->not->toContain('Hi Demo');
});

test('an agent chooses the actual next actor when sending a reply', function (string $status) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create();
    $reply = $ticket->suggestedReplies()->create(['body' => 'Our team will review your request.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    Livewire::test(TicketShow::class, ['ticket' => $ticket])->set('nextReplyStatus', $status)->call('approveAndSend')->assertHasNoErrors();
    expect($ticket->fresh()->status->value)->toBe($status);
    $this->get(route('tickets.status', $ticket->public_token))->assertSee(TicketStatus::from($status)->label())->assertSee('Our team will review your request.');
})->with(['awaiting_staff', 'waiting_on_customer', 'resolved']);

test('a staff-review acknowledgement keeps the public status updating until staff finish', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingStaff]);
    $ticket->messages()->create([
        'visibility' => 'public', 'author_type' => 'agent',
        'body' => 'Our team is reviewing your request.', 'approved_at' => now(),
    ]);
    $component = Livewire::test(App\Livewire\Pages\TicketStatus::class, ['publicToken' => $ticket->public_token]);
    expect($component->instance()->shouldPoll())->toBeTrue();
    $ticket->update(['status' => TicketStatus::Resolved]);
    $component->call('$refresh');
    expect($component->instance()->shouldPoll())->toBeFalse();
});

test('an invalid next-actor selection cannot send a draft', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create();
    $reply = $ticket->suggestedReplies()->create(['body' => 'Our team will review your request.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    Livewire::test(TicketShow::class, ['ticket' => $ticket])->set('nextReplyStatus', 'triaging')->call('approveAndSend')->assertHasErrors('nextReplyStatus');
    expect($reply->fresh()->status)->toBe(SuggestedReplyStatus::Pending)
        ->and($ticket->messages()->where('author_type', 'agent')->count())->toBe(0);
});

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
        ->and($reply->citedChunks())->toHaveSameSize($reply->cited_chunk_ids)
        ->and($reply->citedChunks()->pluck('article.slug')->unique()->all())->toBe(['return-window'])
        ->and($reply->body)->toContain('Hi Jamie,')
        ->and($reply->body)->toContain("Best,\nAlex Rivera\nHarbor Outfitters Support")
        ->and($reply->body)->not->toContain('—Alex at Harbor & Co')
        ->and($reply->body)->toContain('not required')
        ->and($reply->body)->not->toContain('Hi Jamie—')
        ->and($reply->body)->not->toContain('Hi Jamie —')
        ->and($reply->body)->not->toContain('— Alex');

    $this->actingAs(User::query()->first());

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertOk()
        ->assertSee('Policy sources available')
        ->assertSee('Sources')
        ->assertSee('Return window')
        ->assertSee('Box not required')
        ->assertSee('Prepaid labels')
        ->assertSee('High (91%). This is the AI’s own estimate')
        ->assertSee('Support policy match:')
        ->assertSee('What AI noticed in the request')
        ->assertSee('These points help explain the category, priority, and sentiment.')
        ->assertDontSee('They are separate from the policy match score above.')
        ->assertSee('(match score 0.81). This measures how closely the support policies match the request.')
        ->assertSeeNormalizedText('Lucid uses this match to decide whether there is enough supporting information to suggest a reply.')
        ->assertDontSee('This gates suggested drafts')
        ->assertDontSee('retrieval score')
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
        ->and(file_get_contents(resource_path('views/components/demo-banner.blade.php')))
        ->toContain('Live portfolio demo · Harbor Outfitters')
        ->and(file_get_contents(resource_path('views/livewire/pages/welcome.blade.php')))
        ->not->toContain(' — ')
        ->not->toContain('PostgreSQL / pgvector')
        ->not->toContain('live models —')
        ->not->toContain('knowledge — then')
        ->not->toContain('copilot—not a slide deck')
        ->not->toContain('live models—not canned scripts')
        ->and(normalizedVisibleText(file_get_contents(resource_path('views/livewire/pages/demo-environment.blade.php'))))
        ->toContain('OpenAI transcribes your audio into text.')
        ->not->toContain('dictation — not')
        ->and(normalizedVisibleText(file_get_contents(resource_path('views/livewire/pages/demo-safety.blade.php'))))
        ->toContain('These optional tests show how the assistant handles unsafe requests, missing information, and situations requiring human review.')
        ->not->toContain('live product—they are not a second script')
        ->not->toContain('live product —')
        ->and(normalizedVisibleText(file_get_contents(resource_path('views/livewire/pages/demo-workflow.blade.php'))))
        ->toContain('Go to Tickets → Live demo and find the same SF- reference.')
        ->not->toContain('Customer→')
        ->and(normalizedVisibleText(file_get_contents(resource_path('views/livewire/pages/ticket-index.blade.php'))))
        ->toContain('Choose an example, preview it, then open a ticket.')
        ->toContain('Existing examples stay unchanged.')
        ->not->toContain('Clones a fixture')
        ->and(file_get_contents(app_path('Services/DemoScenarioService.php')))
        ->toContain('Shipping/returns')
        ->toContain('shipping/returns policy')
        ->toContain('Detect angry tone, mark high priority, send to a human without an AI reply.')
        ->toContain('Recognize that the store policy does not list available thread colors, then send the ticket to a human without making up an answer.')
        ->toContain('Detect the attempt to change the assistant’s rules, block AI reply generation, and send the ticket to a human.')
        ->not->toContain('Shipping / returns')
        ->not->toContain('Look for')
        ->not->toContain('prepare a billing reply')
        ->and(file_get_contents(resource_path('views/livewire/pages/ticket-show.blade.php')))
        ->toContain('Unsafe instructions detected. No AI reply was created. Please respond manually.')
        ->not->toContain('Prompt-injection suspected')
        ->not->toContain('Draft skipped');

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('PostgreSQL / pgvector')
        ->assertDontSee('live models — not canned scripts');
});
