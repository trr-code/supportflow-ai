<?php

use App\Ai\Agents\SuggestedReplyAgent;
use App\Ai\Agents\SupportChatAgent;
use App\Ai\Agents\SupportChatStreamAgent;
use App\Ai\Agents\TicketTriageAgent;
use App\Enums\TicketCategory;
use App\Enums\TicketDepartment;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\SuggestedReply;
use App\Services\DemoSessionService;
use App\Services\KnowledgeIndexService;
use Database\Seeders\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Embeddings;
use Livewire\Features\SupportTesting\Testable;
use Pest\Evals\Drivers\LaravelAiEmbeddings;
use Pest\Evals\Drivers\LaravelAiJudge;
use Pest\Evals\Plugin as PestEvals;
use Tests\EvalTestCase;
use Tests\TestCase;

require __DIR__.'/Support/stressless.php';

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(EvalTestCase::class)
    ->use(RefreshDatabase::class)
    ->group('evals')
    ->in('Evals');

pest()->evals()
    ->judgeUsing(new LaravelAiJudge(provider: 'openai', model: 'gpt-5.6-luna'))
    ->embeddingsUsing(new LaravelAiEmbeddings(provider: 'openai', model: 'text-embedding-3-small'));

pest()->beforeEach(function (): void {
    if (! PestEvals::isEvalMode()) {
        $this->markTestSkipped('Eval skipped. Run with [--evals] to evaluate against a real model.');
    }

    if (! filled((string) config('ai.providers.openai.key'))) {
        $this->markTestSkipped('Set OPENAI_API_KEY to run Evals.');
    }

    test()->seed(KnowledgeSeeder::class);
})->in('Evals');

pest()->group('stress')->in('Stress');

pest()->beforeEach(function (): void {
    persistStresslessCookiesAcrossIterations();

    if (! stressTestingEnabled()) {
        $this->markTestSkipped('Set STRESS=true and STRESS_URL to run Stressless tests.');
    }
})->in('Stress');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * @return array<string, mixed>
 */
function fakeTriagePayload(array $overrides = []): array
{
    return [
        'category' => TicketCategory::Returns->value,
        'priority' => TicketPriority::Medium->value,
        'sentiment' => TicketSentiment::Neutral->value,
        'department' => TicketDepartment::Returns->value,
        'summary' => 'Customer asking about an unused return.',
        'classification_confidence' => 0.86,
        'decision_factors' => ['Mentions unused pack', 'Asks about return window'],
        'injection_suspected' => false,
        'needs_human' => false,
        ...$overrides,
    ];
}

/**
 * Point stored chunks and later Embeddings::for() calls at the same official fake vector
 * so pgvector tests can assert a retrieval hit without a custom embedding model.
 *
 * @param  list<float>|null  $vector
 * @return list<float>
 */
function fakeMatchingKnowledgeEmbeddings(?array $vector = null): array
{
    $vector ??= Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536));

    KnowledgeChunk::query()->each(function (KnowledgeChunk $chunk) use ($vector): void {
        $chunk->forceFill(['embedding' => $vector])->save();
    });

    Embeddings::fake(fn (): array => [$vector]);

    return $vector;
}

/**
 * Give each stored chunk a unique unit vector and point later Embeddings::for()
 * calls at an unused axis so pgvector ranking cannot treat every passage as a hit.
 */
function assignDistinctKnowledgeEmbeddings(): void
{
    $dimensions = (int) config('supportflow.embeddings.dimensions', 1536);
    $index = 0;

    KnowledgeChunk::query()->orderBy('id')->each(function (KnowledgeChunk $chunk) use ($dimensions, &$index): void {
        $vector = array_fill(0, $dimensions, 0.0);
        $vector[$index % $dimensions] = 1.0;
        $chunk->forceFill(['embedding' => $vector])->save();
        $index++;
    });

    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[min($dimensions - 1, $index + 8)] = 1.0;
    Embeddings::fake(fn (): array => [$queryVector]);
}

function seedHarborKnowledgeCatalog(): void
{
    test()->seed(KnowledgeSeeder::class);
    assignDistinctKnowledgeEmbeddings();
}

/**
 * @return list<string>
 */
function citedArticleSlugs(ChatMessage|SuggestedReply $record): array
{
    $ids = array_values(array_map(intval(...), $record->cited_chunk_ids ?? []));

    if ($ids === []) {
        return [];
    }

    return KnowledgeChunk::query()
        ->with('article')
        ->whereIn('id', $ids)
        ->get()
        ->map(fn (KnowledgeChunk $chunk): string => $chunk->article->slug)
        ->unique()
        ->values()
        ->all();
}

function evalDemoSession(): DemoSession
{
    return DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
}

function seedHarborPolicyArticles(): void
{
    $rows = [
        ['Return window', 'return-window', 'returns', "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.\n## Box not required\nThe original shipping box is helpful but not required. A sturdy carton is fine.\n## Prepaid labels\nWe email a prepaid UPS label after the return is approved in the order portal."],
        ['Exchanges', 'exchanges', 'returns', "Size exchanges for packs, shells, and footwear are free within 30 days if the item is unused.\n## How to start\nStart an exchange from the order in the Harbor app or email support with the order number."],
        ['Shipping times', 'shipping-times', 'shipping', "Standard ground shipping is 3–6 business days inside the contiguous US.\n## Expedited\n2-day and overnight options appear at checkout when inventory is in the Kent warehouse."],
        ['Warranty', 'warranty', 'general', "Harbor hardgoods carry a 2-year manufacturing warranty against seam and hardware failure in normal use.\n## Not covered\nImpacts, misuse, and normal wear are not covered. We may offer a discounted replacement."],
    ];

    fakeMatchingKnowledgeEmbeddings();

    foreach ($rows as $row) {
        $article = KnowledgeArticle::query()->create([
            'title' => $row[0],
            'slug' => $row[1],
            'category' => $row[2],
            'body' => $row[3],
            'is_published' => true,
            'is_seeded' => true,
        ]);
        app(KnowledgeIndexService::class)->syncArticle($article);
    }

    fakeMatchingKnowledgeEmbeddings();
}

function fakeSupportAi(array $triage = [], array $reply = [], array $chat = []): void
{
    TicketTriageAgent::fake([
        $triage === [] ? fakeTriagePayload() : $triage,
    ])->preventStrayPrompts();

    SuggestedReplyAgent::fake([
        $reply === [] ? [
            'body' => 'You can return unused Harbor Trail Packs within 30 days with tags attached.',
            'cited_chunk_ids' => [1],
            'grounded' => true,
            'refusal_reason' => '',
        ] : $reply,
    ])->preventStrayPrompts();

    $chatPayload = $chat === [] ? [
        'body' => 'Unused returns are accepted within 30 days with tags attached.',
        'cited_chunk_ids' => [1],
        'grounded' => true,
    ] : $chat;

    SupportChatAgent::fake([$chatPayload])->preventStrayPrompts();

    $cite = ($chatPayload['cited_chunk_ids'] ?? []) === []
        ? 'none'
        : implode(',', $chatPayload['cited_chunk_ids']);

    SupportChatStreamAgent::fake([
        ($chatPayload['body'] ?? 'Unused returns are accepted within 30 days with tags attached.')."\nCITES: {$cite}",
    ])->preventStrayPrompts();
}

function postChatStream(string $question, ?string $demoSessionId = null): TestResponse
{
    $pending = test()->withCredentials();

    if (is_string($demoSessionId) && $demoSessionId !== '') {
        $pending = $pending->withCookie(DemoSessionService::COOKIE, $demoSessionId);
    }

    return $pending->withHeaders([
        'Accept' => 'text/event-stream, application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->postJson(route('chat.stream'), ['question' => $question]);
}

function completeWidgetChatTurn(mixed $component, ?string $question = null): TestResponse
{
    $question ??= (string) $component->get('pendingQuestion');

    if ($question === '') {
        $question = (string) $component->get('question');
    }
    $response = postChatStream($question, (string) $component->get('demoSessionId'));

    if ($response->getStatusCode() === 200) {
        $response->assertStreamed();
        $response->streamedContent();
        $component->call('finishTurn');
    } else {
        $message = $response->json('errors.question.0')
            ?? $response->json('message')
            ?? 'Chat request failed.';
        $component->call('reportStreamError', $message);
    }

    return $response;
}

Testable::macro('streamTurn', function (?string $question = null) {
    completeWidgetChatTurn($this, $question);

    return $this;
});
