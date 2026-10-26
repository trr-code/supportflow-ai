<?php

use App\Ai\Agents\SupportChatStreamAgent;
use App\Enums\ReplacementProposalStatus;
use App\Jobs\RunReplacementAgent;
use App\Livewire\Pages\DemoWorkspace;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Models\ReplacementCase;
use App\Models\ReplacementRequest;
use App\Models\Workspace;
use App\Services\ChatService;
use App\Services\DemoSessionService;
use App\Services\ReplacementCaseService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Embeddings;
use Livewire\Livewire;

test('a policy question does not attach order tools or open a replacement case', function () {
    Embeddings::fake();
    $prompts = [];
    SupportChatStreamAgent::fake(function (string $prompt) use (&$prompts): string {
        $prompts[] = $prompt;

        return "Unused items can be returned.\nCITES: none";
    });

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);

    $message = app(ChatService::class)->ask($session, 'How long is the return window for unused items?');

    expect($prompts)->toBe([])
        ->and($message->body)->toContain('I don’t have a documented answer')
        ->and(ReplacementCase::query()->count())->toBe(0);
});

test('an order question can use replacement tools without reading a private file', function () {
    Embeddings::fake();
    $workspace = Workspace::factory()->create();
    $private = KnowledgeArticle::query()->create([
        'workspace_id' => $workspace->id,
        'title' => 'Private ledger',
        'slug' => 'private-ledger-'.Str::lower(Str::random(6)),
        'category' => 'general',
        'body' => 'Acme secret ledger balance is 9999.',
        'is_published' => true,
        'is_seeded' => false,
    ]);
    $private->chunks()->create([
        'heading' => null,
        'body' => 'Acme secret ledger balance is 9999.',
        'token_count' => 8,
    ]);

    $prompts = [];
    SupportChatStreamAgent::fake(function (string $prompt) use (&$prompts): string {
        $prompts[] = $prompt;

        return "I checked the order.\nCITES: none";
    });

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);

    $message = app(ChatService::class)->ask($session, 'My bag arrived damaged. Order HB-10482. Can you replace it?');

    expect($prompts)->toHaveCount(1)
        ->and($prompts[0])->toContain('Order tools are available')
        ->and($prompts[0])->toContain('No policy passages matched')
        ->and($prompts[0])->not->toContain('Acme secret ledger')
        ->and($message->body)->toContain('I don’t have a documented answer')
        ->and(ReplacementCase::query()->where('demo_session_id', $session->id)->exists())->toBeTrue();
});

test('holding a replacement proposal does not save a request', function () {
    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
    $service = app(ReplacementCaseService::class);
    $case = $service->forSession($session);

    expect($service->holdProposal($case->id, $case->generation, new PendingApproval('call-missing', 'CreateReplacementRequest', [
        'order_number' => 'HB-99999',
        'quantity' => 1,
        'reason' => 'Arrived damaged',
    ])))->toBeFalse();

    expect($service->holdProposal($case->id, $case->generation, new PendingApproval('call-ready', 'CreateReplacementRequest', [
        'order_number' => 'HB-10482',
        'quantity' => 1,
        'reason' => 'Arrived damaged',
    ])))->toBeTrue()
        ->and($case->proposals()->where('status', ReplacementProposalStatus::Pending)->count())->toBe(1)
        ->and(ReplacementRequest::query()->count())->toBe(0);
});

test('the harbor panel can cancel a waiting proposal without saving it', function () {
    Bus::fake([RunReplacementAgent::class]);

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
    $case = app(ReplacementCaseService::class)->forSession($session);
    $case->proposals()->create([
        'generation' => $case->generation,
        'tool_call_id' => 'call-panel',
        'argument_hash' => ReplacementCaseService::hashArguments([
            'order_number' => 'HB-10482',
            'quantity' => 1,
            'reason' => 'Arrived damaged',
        ]),
        'status' => ReplacementProposalStatus::Pending,
        'order_number' => 'HB-10482',
        'sku' => 'driftwood-duffel',
        'item_name' => 'Driftwood Duffel',
        'quantity' => 1,
        'reason' => 'Arrived damaged',
        'action' => 'Save a replacement request',
        'snapshot' => ['order_number' => 'HB-10482'],
        'expires_at' => now()->addMinutes(10),
    ]);

    Livewire::withCookies([DemoSessionService::COOKIE => $session->id])
        ->test(DemoWorkspace::class)
        ->assertSee('Driftwood Duffel')
        ->call('cancelReplacement')
        ->assertDontSee('Driftwood Duffel');

    expect(ReplacementRequest::query()->count())->toBe(0)
        ->and($case->proposals()->first()?->status)->toBe(ReplacementProposalStatus::Cancelled);

    Bus::assertDispatched(RunReplacementAgent::class);
});

test('opening the homepage does not write a harbor chat', function () {
    $this->get(route('home'))->assertOk();

    expect(ChatMessage::query()->count())->toBe(0);
});
