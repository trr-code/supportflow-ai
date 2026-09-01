<?php

use App\Enums\SuggestedReplyStatus;
use App\Jobs\GenerateSuggestedReply;
use App\Jobs\ProcessTicketIntake;
use App\Livewire\Pages\TicketShow;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('regenerate dispatches within the allowed limit', function () {
    Queue::fake();

    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $reply = SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => 'Original grounded draft',
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [],
    ]);

    $this->actingAs($agent);
    $key = 'regenerate|'.$agent->id.'|'.$ticket->id;
    $decay = (int) config('supportflow.rate_limits.regenerate.decay_seconds');
    RateLimiter::clear($key);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->call('regenerate')
        ->assertSet('flash', 'Regenerating a grounded draft…');

    Queue::assertPushed(GenerateSuggestedReply::class, 1);
    Queue::assertPushed(GenerateSuggestedReply::class, function (GenerateSuggestedReply $job) use ($ticket, $reply): bool {
        return $job->ticketId === $ticket->id
            && $job->force === true
            && $job->regeneratedFromId === $reply->id;
    });
    Queue::assertNotPushed(ProcessTicketIntake::class);

    expect($reply->fresh()->status)->toBe(SuggestedReplyStatus::Pending)
        ->and($reply->fresh()->body)->toBe('Original grounded draft')
        ->and(RateLimiter::attempts($key))->toBe(1)
        ->and($decay)->toBe(600);
});

test('regenerate blocks the first attempt over the limit without new AI work', function () {
    Queue::fake();

    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $reply = SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => 'Original grounded draft',
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [12, 15],
    ]);

    $this->actingAs($agent);
    $max = (int) config('supportflow.rate_limits.regenerate.max_attempts');
    $decay = (int) config('supportflow.rate_limits.regenerate.decay_seconds');
    $key = 'regenerate|'.$agent->id.'|'.$ticket->id;
    RateLimiter::clear($key);

    for ($i = 0; $i < $max; $i++) {
        RateLimiter::hit($key, $decay);
    }

    $eventCount = $ticket->events()->count();
    $runCount = $ticket->aiRuns()->count();

    $component = Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->call('regenerate');

    expect($component->get('flash'))
        ->toContain('Regeneration limit reached')
        ->toContain('Try again');

    Queue::assertNothingPushed();
    Queue::assertNotPushed(GenerateSuggestedReply::class);
    Queue::assertNotPushed(ProcessTicketIntake::class);

    $reply->refresh();

    expect($reply->status)->toBe(SuggestedReplyStatus::Pending)
        ->and($reply->body)->toBe('Original grounded draft')
        ->and($reply->cited_chunk_ids)->toBe([12, 15])
        ->and($ticket->events()->count())->toBe($eventCount)
        ->and($ticket->aiRuns()->count())->toBe($runCount)
        ->and($ticket->suggestedReplies()->count())->toBe(1);
});
