<?php

use App\Ai\Agents\SupportChatStreamAgent;
use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\TicketStatus;
use App\Jobs\ProcessTicketIntake;
use App\Models\AiRun;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Services\AiUsageRecorder;
use App\Services\KnowledgeIndexService;
use Illuminate\Support\Facades\Exceptions;

test('the stale closer fails an old running row and leaves a newer running row and a completed row', function () {
    $ticket = Ticket::factory()->create();

    $old = AiRun::query()->create([
        'feature' => AiRunFeature::Triage,
        'ticket_id' => $ticket->id,
        'provider' => 'openai',
        'status' => AiRunStatus::Running,
        'started_at' => now()->subMinutes(30),
    ]);
    $newer = AiRun::query()->create([
        'feature' => AiRunFeature::Triage,
        'ticket_id' => $ticket->id,
        'provider' => 'openai',
        'status' => AiRunStatus::Running,
        'started_at' => now(),
    ]);
    $completed = AiRun::query()->create([
        'feature' => AiRunFeature::Triage,
        'ticket_id' => $ticket->id,
        'provider' => 'openai',
        'status' => AiRunStatus::Completed,
        'started_at' => now()->subHour(),
        'completed_at' => now()->subHour(),
    ]);

    AiRun::query()->whereKey($old->id)->update(['updated_at' => now()->subMinutes(21)]);
    AiRun::query()->whereKey($completed->id)->update(['updated_at' => now()->subHour()]);

    app(AiUsageRecorder::class)->failAbandoned();

    expect($old->fresh()->status)->toBe(AiRunStatus::Failed)
        ->and($old->fresh()->error)->toBe('Closed as stale.')
        ->and($newer->fresh()->status)->toBe(AiRunStatus::Running)
        ->and($completed->fresh()->status)->toBe(AiRunStatus::Completed);
});

test('a late intake failure leaves a newer running row and a completed row untouched', function () {
    $ticket = Ticket::factory()->create();

    $completed = AiRun::query()->create([
        'feature' => AiRunFeature::Triage,
        'ticket_id' => $ticket->id,
        'provider' => 'openai',
        'status' => AiRunStatus::Completed,
        'completed_at' => now(),
    ]);
    $owned = AiRun::query()->create([
        'feature' => AiRunFeature::Triage,
        'ticket_id' => $ticket->id,
        'provider' => 'openai',
        'status' => AiRunStatus::Queued,
    ]);

    $job = new ProcessTicketIntake($ticket->id);
    $owned->forceFill(['status' => AiRunStatus::Running])->save();

    $newer = AiRun::query()->create([
        'feature' => AiRunFeature::Triage,
        'ticket_id' => $ticket->id,
        'provider' => 'openai',
        'status' => AiRunStatus::Running,
        'started_at' => now(),
    ]);

    $job->failed(new RuntimeException('worker lost'));

    expect($job->ownedRunId)->toBe($owned->id)
        ->and($owned->fresh()->status)->toBe(AiRunStatus::Failed)
        ->and($newer->fresh()->status)->toBe(AiRunStatus::Running)
        ->and($completed->fresh()->status)->toBe(AiRunStatus::Completed)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::AiFailed);
});

test('a chat exception after start fails that run and a second failure leaves it completed', function () {
    Exceptions::fake();
    config(['supportflow.retrieval.min_similarity' => 0.05]);

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'chat-failure-returns',
        'category' => 'returns',
        'body' => 'Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    SupportChatStreamAgent::fake(function (): never {
        throw new RuntimeException('The model stopped.');
    });

    postChatStream('How long is the unused return window?')
        ->assertOk()
        ->streamedContent();

    $run = AiRun::query()->where('feature', AiRunFeature::Chat)->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(AiRunStatus::Failed)
        ->and($run->error)->toContain('The model stopped.');

    $run->forceFill([
        'status' => AiRunStatus::Completed,
        'error' => null,
        'completed_at' => now(),
    ])->save();

    app(AiUsageRecorder::class)->fail($run->fresh(), 'too late');

    expect($run->fresh()->status)->toBe(AiRunStatus::Completed)
        ->and($run->fresh()->error)->toBeNull();
});
