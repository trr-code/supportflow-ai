<?php

use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketIndex;
use App\Livewire\Pages\TicketShow;
use App\Models\AiRun;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

test('reply history guidance matches empty unsent sent and overflow states', function (int $count, bool $hasReply) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    for ($index = 0; $index < $count; $index++) {
        $ticket->messages()->create(['visibility' => 'public', 'author_type' => $hasReply && $index > 0 ? 'agent' : 'customer', 'body' => 'Visible conversation '.$index, 'approved_at' => now()]);
    }
    $ticket->messages()->create(['visibility' => 'internal', 'author_type' => 'agent', 'body' => 'Private history fixture']);
    $component = Livewire::test(TicketShow::class, ['ticket' => $ticket])->call('showWorkspace', 'conversation')
        ->assertSee('Nothing is sent from Reply history.');
    if ($count === 0) {
        $component->assertSee('No messages are available for this ticket yet.');
    } elseif (! $hasReply) {
        $component->assertSee('No reply has been sent yet.');
    } else {
        $component->assertDontSee('No reply has been sent yet.');
    }
    $component->assertSeeText('If this ticket has more than six, Show earlier messages appears above the list.')
        ->assertViewHas('hasEarlierReplies', $count > 6)
        ->assertViewHas('visiblePublicMessages', fn ($messages): bool => $messages->count() === min(6, $count));
    $component->call('showEarlierReplies')
        ->assertViewHas('visiblePublicMessages', fn ($messages): bool => $messages->count() === min(12, $count))
        ->call('showEarlierReplies')
        ->assertViewHas('visiblePublicMessages', fn ($messages): bool => $messages->count() === $count)
        ->call('showRecentReplies')
        ->assertViewHas('visiblePublicMessages', fn ($messages): bool => $messages->count() === min(6, $count));
    expect($ticket->messages()->count())->toBe($count + 1);
})->with([[0, false], [1, false], [6, true], [7, true], [13, true]]);

test('ticket details and notes keep feedback separate from reply actions', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview, 'priority' => TicketPriority::Medium]);
    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->call('showTools', 'details')->call('saveOverrides')
        ->assertSet('detailsFeedback', 'Details saved. No reply was sent.')
        ->assertSet('detailsFeedbackRevision', 1)
        ->assertSet('flash', null)->assertSeeHtml('data-details-feedback')
        ->call('showWorkspace', 'notes')->set('note', 'Team note')->call('addNote')
        ->assertSet('noteFeedback', 'Internal note saved. Customers never see notes.')
        ->assertSet('noteFeedbackRevision', 1)
        ->assertSet('flash', null)->assertSeeHtml('data-note-feedback');
    expect($ticket->fresh()->priority->value)->toBe('medium');
    expect($ticket->messages()->where('visibility', 'public')->count())->toBe(0);
});

test('queue reset feedback follows the reset action rather than preceding the queue controls', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(TicketIndex::class)->set('notice', 'Reset result for this queue.')
        ->assertSeeInOrder(['Clear inactive demo data', 'data-reset-feedback', 'Reset result for this queue.'], false);
});

test('ticket tool instructions follow the selected task without changing ticket data', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $original = $ticket->fresh()->getAttributes();
    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->call('showTools', 'details')->assertSet('showToolInstructions', true)
        ->assertSeeNormalizedText('Update ticket details')->assertSee('Save details is not needed for status changes.')
        ->call('showTools', 'activity')->assertSeeNormalizedText('Review ticket activity')
        ->assertSee('System means Lucid performed the step automatically.')
        ->call('showWorkspace', 'notes')->assertSet('showToolInstructions', false)
        ->assertSeeNormalizedText('Keep private team notes')
        ->call('showTools', 'details')->assertSet('showToolInstructions', true)
        ->call('showTools', 'sources')->assertSet('showToolInstructions', false)
        ->call('showTools', 'invalid')->assertSet('tools', 'sources');
    expect($ticket->fresh()->getAttributes())->toBe($original);
});

test('ticket activity uses business labels and keeps diagnostics out of the page', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Resolved]);
    $run = AiRun::query()->create(['ticket_id' => $ticket->id, 'feature' => 'triage', 'status' => 'completed', 'provider' => 'openai', 'model' => 'internal-diagnostic-model', 'input_tokens' => 123, 'output_tokens' => 45, 'completed_at' => now()]);
    foreach (TicketEventType::cases() as $type) {
        $ticket->events()->create(['type' => $type, 'actor' => 'system', 'ai_run_id' => $run->id]);
    }
    $component = Livewire::test(TicketShow::class, ['ticket' => $ticket])->call('showTools', 'activity')
        ->assertSee('Show earlier activity')->call('showEarlierActivity')
        ->assertSee('AI assessment completed')->assertSee('Policy search completed')->assertSee('Suggested reply prepared')
        ->assertSee('Ticket details updated')->assertViewHas('hasEarlierActivity', false)
        ->assertDontSee('Technical details')->assertDontSee('AI runs')->assertDontSee('ticket-runs')
        ->assertDontSee('internal-diagnostic-model')->assertDontSee('Tokens in/out');
    foreach (TicketEventType::cases() as $type) {
        $component->assertSee($type->label());
    }
    expect($run->fresh()->input_tokens)->toBe(123);
    expect($ticket->events()->count())->toBe(count(TicketEventType::cases()));
});

test('earlier activity expands inline in stable batches without changing history', function (int $count) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Resolved]);
    $ids = [];
    $time = now();
    for ($index = 0; $index < $count; $index++) {
        $event = $ticket->events()->make(['type' => TicketEventType::StatusChanged, 'actor' => 'Activity '.$index]);
        $event->created_at = $time;
        $event->save();
        $ids[] = $event->id;
    }
    $other = Ticket::factory()->create();
    $other->events()->create(['type' => TicketEventType::Created, 'actor' => 'Other ticket actor']);
    $component = Livewire::test(TicketShow::class, ['ticket' => $ticket])->call('showTools', 'activity');
    for ($visible = 8; ; $visible += 8) {
        $expected = array_slice(array_reverse($ids), 0, $visible);
        $component->assertViewHas('events', fn ($events): bool => $events->modelKeys() === $expected)
            ->assertViewHas('hasEarlierActivity', $count > $visible)
            ->assertDontSee('Other ticket actor')->assertDontSeeHtml('name="ticket-activity"');
        if ($count <= $visible) {
            break;
        }
        $component->call('showEarlierActivity');
    }
    $component->call('showTools', 'details')->call('showTools', 'activity')
        ->assertViewHas('events', fn ($events): bool => $events->count() === $count);
    $component->call('showRecentActivity')->assertSet('activityLimit', 8)
        ->assertViewHas('events', fn ($events): bool => $events->modelKeys() === array_slice(array_reverse($ids), 0, 8))
        ->assertDontSeeHtml('data-recent-activity');
    if ($count > 8) {
        $component->call('showEarlierActivity')->assertSeeHtml('data-recent-activity');
    }
    expect($ticket->events()->orderBy('id')->pluck('id')->all())->toBe($ids);
    expect($ticket->messages()->count())->toBe(0);
})->with([0, 8, 9, 17]);

test('internal ticket has a descriptive title one main heading and search exclusion', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Return policy question']);
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('agent.tickets.show', $ticket))->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow" />', false)
        ->assertSee($ticket->reference.' - Lucid')
        ->assertSee('id="ticket-subject"', false);
    $response->assertSee('Review the customer request, check the draft, and choose how to reply.');
    $response->assertSee('<h1 id="ticket-review-title">Ticket review</h1>', false)
        ->assertSee('<h2 id="ticket-subject"', false)
        ->assertSee('Fictional demo');
    $response->assertSeeInOrder(['Ticket review</h1>', 'Review the customer request, check the draft, and choose how to reply.', 'id="workspace-review"', 'id="workspace-conversation"', 'id="workspace-notes"', 'id="ticket-subject"', 'Return policy question', 'Customer request'], false);
    expect(preg_match_all('/<h1\b/i', $response->getContent()))->toBe(1);
    $this->get(route('home'))->assertDontSee('<meta name="robots" content="noindex, nofollow" />', false);
});

test('agent pages share the title demo pill and description header', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create();
    foreach ([route('dashboard'), route('agent.tickets.index'), route('agent.tickets.show', $ticket)] as $url) {
        $response = $this->get($url)->assertOk()->assertSee('lucid-page-header', false)->assertSee('Fictional demo');
        expect(preg_match_all('/<h1\b/i', $response->getContent()))->toBe(1);
    }
    $this->get(route('dashboard'))->assertSee('Harbor Outfitters support at a glance.')->assertDontSee('Harbor &amp;amp; Co', false);
});

test('remaining support screens share the showroom frame without a second floating chat', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create();

    foreach ([route('dashboard'), route('agent.tickets.index'), route('agent.tickets.show', $ticket), route('knowledge.index'), route('tickets.status', $ticket->public_token)] as $url) {
        $response = $this->get($url)->assertSee('showroom-secondary', false)->assertSee('lucid-wordmark', false)->assertSee('Browse policies')->assertDontSee('chat-dock-panel')->assertSee('Step-by-step instructions')->assertSee('Show instructions');
        expect(preg_match_all('/<h1\b/i', $response->getContent()))->toBe(1);
    }
});

test('standalone ticket guidance follows its draft and sent states without exposing private data', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $reply = SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'Private proposed response.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    $this->get(route('agent.tickets.show', $ticket))->assertSee('Review and edit the reply')->assertSee('Click inside the reply to edit it before sending.')
        ->assertSeeText('View reasoning explains those choices')
        ->assertSeeText('Use Sources below the reply to check')
        ->assertDontSee('wire:poll.5s.visible', false);
    $this->get(route('tickets.status', $ticket->public_token))->assertSee('Watch for the reply')->assertSee('This page updates automatically while AI prepares a draft')->assertDontSee('Private proposed response.');
    $reply->update(['status' => SuggestedReplyStatus::Approved]);
    TicketMessage::query()->create(['ticket_id' => $ticket->id, 'author_type' => 'agent', 'visibility' => 'public', 'body' => 'Approved response.', 'approved_at' => now()]);
    $this->get(route('agent.tickets.show', $ticket))->assertSee('Read the sent reply')->assertDontSee('Review and edit the reply');
    $this->get(route('tickets.status', $ticket->public_token))->assertSee('Read the reply')->assertDontSee('Watch for the reply')->assertSee('Approved response.')->assertDontSee('Private proposed response.');
});

test('invoice workspace guides the first upload without presenting unavailable review steps', function () {
    $this->get(route('demo.invoices'))->assertSee('Choose an invoice')->assertDontSee('Review the details')->assertDontSee('Download the results')->assertSee('Extract fields')->assertSee('Files and privacy')
        ->assertDontSee('toggleGuide', false);
    $this->get(route('demo.documents'))->assertSeeText('Add your files using Choose files, then Upload files.')
        ->assertDontSee('Check the sources')->assertDontSee('Start fresh')
        ->assertDontSee('toggleGuide', false);
});

test('dashboard and queue offer their own current-page instructions', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('dashboard'))->assertSee('Step-by-step instructions')->assertSee('Explore the dashboard')
        ->assertSee('This is the agent view.')
        ->assertSee('Check the ticket totals')->assertSee('Find work in Needs attention')->assertSee('Understand status and priority')
        ->assertSee('Unassigned means no priority has been set.')
        ->assertSee('Live demo submissions shows how many tickets people have submitted while trying this demo.')
        ->assertSee('Replies written entirely by a person are not included.')
        ->assertDontSee('Open a ticket to check the customer request')
        ->assertSee('Hide instructions')->assertSee('Show instructions');
    $this->get(route('agent.tickets.index'))->assertDontSee('Explore the dashboard')
        ->assertSee('Step-by-step instructions')->assertSee('Filter the requests')
        ->assertSee('Read the ticket list')->assertSee('Open a request')
        ->assertSee('Try an example or clear inactive demo data')->assertSee('Show instructions');
});

test('queue instructions follow scenario preview and returning without creating a ticket', function () {
    $this->actingAs(User::factory()->create());
    $count = Ticket::query()->count();
    Livewire::test(TicketIndex::class)
        ->assertSee('Filter the requests')
        ->set('showScenarios', true)
        ->assertSee('Try a support scenario')
        ->assertSee('Follow these steps to choose an example and see how Lucid handles the request.')
        ->assertSee('Read the example')->assertSee('Return to the queue')
        ->assertDontSee('Filter the requests')
        ->call('selectScenario', 'angry')
        ->assertSee('send to a human without an AI reply')
        ->assertSee('Choosing a name only previews it.')
        ->call('closeScenarios')->assertSee('Filter the requests')
        ->assertDontSee('Try a support scenario');
    expect(Ticket::query()->count())->toBe($count);
});

test('customer status separates the sent reply milestone from an unsent draft', function () {
    $ticket = Ticket::factory()->create();
    TicketMessage::query()->create(['ticket_id' => $ticket->id, 'author_type' => 'agent', 'visibility' => 'internal', 'body' => 'Private draft must stay hidden.', 'approved_at' => null]);
    $this->get(route('tickets.status', $ticket->public_token))->assertSee('Request received')->assertSee('Waiting for reply')->assertDontSee('Reply sent');
    TicketMessage::query()->create(['ticket_id' => $ticket->id, 'author_type' => 'agent', 'visibility' => 'public', 'body' => 'Your approved support reply.', 'approved_at' => now()]);
    $this->get(route('tickets.status', $ticket->public_token))->assertSee('Reply sent')->assertSee('Your approved support reply.')->assertDontSee('Private draft must stay hidden.');
});
