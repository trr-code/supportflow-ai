<?php

use App\Ai\Agents\WorkspaceChatAgent;
use App\Enums\WorkspaceDocumentStatus;
use App\Models\KnowledgeArticle;
use App\Models\Workspace;
use App\Services\RetrievalService;
use App\Services\WorkspaceChatService;
use App\Services\WorkspaceDocumentStore;
use App\Support\ChatSummaryQuery;
use App\Support\KnowledgeCorpus;
use App\Support\WorkspaceAnswerControls;
use App\Support\WorkspaceCopy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    Storage::fake('knowledge');
    $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions'));
    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $vector));
    config(['supportflow.workspaces.min_free_bytes' => 0]);
});

test('multipart rehearsal questions retain after-hours access alongside payment and cancellation', function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('rehearsal.md', file_get_contents(base_path('tests/Fixtures/rehearsal-standard.md')))]);
    $hits = app(RetrievalService::class)->search('What are the rehearsal deposit and equipment-hold requirements, cancellation rules, and after-hours entry requirements?', KnowledgeCorpus::workspace($workspace->id));
    expect($hits->pluck('chunk.body')->implode(' '))->toContain('$180', '$60', '$45', 'south intercom', 'duty lead');
});

test('generic document overviews retrieve all short-file sections without topical similarity', function (string $question) {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('rehearsal.md', file_get_contents(base_path('tests/Fixtures/rehearsal-standard.md')))]);
    $article = $workspace->documents()->sole()->article;
    $article->chunks()->update(['embedding' => null]);
    $hits = app(RetrievalService::class)->search($question, KnowledgeCorpus::workspace($workspace->id));
    expect($hits->pluck('chunk.id')->all())->toBe($article->chunks()->orderBy('id')->pluck('id')->all())
        ->and($hits->pluck('chunk.body')->implode(' '))->toContain('$180', 'south intercom', 'step-free');
})->with(["What's this about?", "What's in this document?", 'Summarize this document’s main points.', "Summarize this document's main points.", 'Summerize this document’s main points.', "So summarize a document's main points.", 'So, summarize this document main points', 'Okay, could you please summarize this document?', 'Well, summarize this document.', 'Summarize a document.', 'Summarize an uploaded report.', 'So summarize all documents.', 'Summarize this documents main points', 'Summarize this document main points', 'Summarize these documents main points', 'Please summerise this document.', 'Could you please summarize this PDF?', 'Explain the main points in this document.', 'Summarize the main points in this document.', 'Please summarize my file.', 'Could you give me an overview of the uploaded document?', 'Can you tell me what this document is about?']);

test('overview detail follow-ups keep the full document after stopped attempts', function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('rehearsal.md', file_get_contents(base_path('tests/Fixtures/rehearsal-standard.md')))]);
    $article = $workspace->documents()->sole()->article;
    $article->chunks()->update(['embedding' => null]);
    $chunk = $article->chunks()->where('heading', 'Deposits and deadlines')->firstOrFail();
    $service = app(WorkspaceChatService::class);
    $thread = $service->conversationFor($workspace);
    $thread->messages()->create(['role' => 'user', 'body' => 'Summarize this documents main points']);
    $thread->messages()->create(['role' => 'assistant', 'body' => 'The deposit is $180.', 'cited_chunk_ids' => [$chunk->id]]);
    $thread->messages()->create(['role' => 'user', 'body' => 'Look better']);
    $thread->messages()->create(['role' => 'assistant', 'body' => 'A partial answer', 'is_stopped' => true, 'cited_chunk_ids' => []]);
    WorkspaceChatAgent::fake(function (string $prompt) use ($chunk): string {
        expect($prompt)->toContain('south intercom', 'step-free', '$180', '$60');

        return 'The deposit is $180 and the equipment authorization is $60.'."\nCITES: {$chunk->id}";
    })->preventStrayPrompts();
    $answer = $service->ask($workspace, 'Explain those points in detail.', WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($answer->body)->toContain('$180', '$60')->not->toContain(WorkspaceCopy::GAP);
});

test('a follow-up without a previous subject asks for clarification rather than guessing', function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('rehearsal.md', file_get_contents(base_path('tests/Fixtures/rehearsal-standard.md')))]);
    WorkspaceChatAgent::fake()->preventStrayPrompts();
    $answer = app(WorkspaceChatService::class)->ask($workspace, 'Explain those points in detail.', WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($answer->body)->toContain('Which topic or points')->not->toContain(WorkspaceCopy::GAP)
        ->and($answer->cited_chunk_ids)->toBe([]);
    WorkspaceChatAgent::assertNeverPrompted();
});

test('a workspace summary reaches the agent and keeps supporting references', function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('rehearsal.md', file_get_contents(base_path('tests/Fixtures/rehearsal-standard.md')))]);
    $chunk = $workspace->documents()->sole()->article->chunks()->where('heading', 'Deposits and deadlines')->firstOrFail();
    WorkspaceChatAgent::fake(function (string $prompt) use ($chunk): string {
        expect($prompt)->toContain('step-free', 'south intercom', '$180');

        return 'The rehearsal standard covers a refundable $180 deposit and equipment, cancellation, door access and accessibility rules.'."\nCITES: {$chunk->id}";
    })->preventStrayPrompts();
    $answer = app(WorkspaceChatService::class)->ask($workspace, "What's this about?", WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($answer->body)->toContain('$180')->not->toContain(WorkspaceCopy::GAP)
        ->and($answer->cited_chunk_ids)->toContain($chunk->id);
});

test('ambiguous summaries ask for a file while named and all-file summaries remain scoped', function () {
    $workspace = Workspace::factory()->create();
    $other = Workspace::factory()->create();
    foreach ([[$workspace, 'rehearsal.md', 'The deposit is $180.'], [$workspace, 'access.txt', 'Use the south intercom after 8:15 p.m.'], [$other, 'private.txt', 'Secret other client text.']] as [$owner, $name, $body]) {
        app(WorkspaceDocumentStore::class)->storeMany($owner, [UploadedFile::fake()->createWithContent($name, $body)]);
    }
    WorkspaceChatAgent::fake()->preventStrayPrompts();
    $answer = app(WorkspaceChatService::class)->ask($workspace, "What's this about?", WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($answer->body)->toContain('Which document')->and($answer->cited_chunk_ids)->toBe([]);
    $natural = app(WorkspaceChatService::class)->ask($workspace, 'Summarize this documents main points', WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($natural->body)->toContain('Which document')->and($natural->cited_chunk_ids)->toBe([]);
    $indefinite = app(WorkspaceChatService::class)->ask($workspace, "So summarize a document's main points.", WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($indefinite->body)->toContain('Which document')->and($indefinite->cited_chunk_ids)->toBe([]);
    WorkspaceChatAgent::assertNeverPrompted();
    $retrieval = app(RetrievalService::class);
    $named = $retrieval->search('Summarize rehearsal.md', KnowledgeCorpus::workspace($workspace->id));
    expect($named->pluck('chunk.body')->all())->toBe(['The deposit is $180.']);
    expect($retrieval->search('So, summarize access.txt', KnowledgeCorpus::workspace($workspace->id))->pluck('chunk.body')->all())->toBe(['Use the south intercom after 8:15 p.m.']);
    $all = $retrieval->search('Summarize all documents', KnowledgeCorpus::workspace($workspace->id));
    expect($all)->toHaveCount(2)->and($all->pluck('chunk.body')->implode(' '))->not->toContain('Secret');
    $workspace->documents()->where('original_name', 'access.txt')->update(['status' => WorkspaceDocumentStatus::Failed]);
    expect($retrieval->search('Summarize all documents', KnowledgeCorpus::workspace($workspace->id)))->toHaveCount(1)
        ->and($retrieval->search('Summarize missing.pdf', KnowledgeCorpus::workspace($workspace->id)))->toBeEmpty();
    $workspace->update(['expires_at' => now()->subMinute()]);
    expect($retrieval->search('Summarize all documents', KnowledgeCorpus::workspace($workspace->id)))->toBeEmpty();
});

test('conversational summary recognition preserves named scope and does not turn ordinary questions into overviews', function () {
    expect(ChatSummaryQuery::target('So, summarize the Returns policy.'))->toBe('the returns policy')
        ->and(ChatSummaryQuery::target('So what is the deposit?'))->toBeNull()
        ->and(ChatSummaryQuery::target('Do not summarize this document.'))->toBeNull()
        ->and(ChatSummaryQuery::target('Summarize a document called rehearsal.md'))->toBe('a document called rehearsal.md')
        ->and(ChatSummaryQuery::target('Software summarizes this document.'))->toBeNull();
});

test('large summaries sample beginning and ending sections and disclose incomplete coverage', function () {
    $workspace = Workspace::factory()->create();
    $body = collect(range(1, 40))->map(fn (int $i): string => "## Section {$i}\nSection {$i} contains rehearsal rule {$i}.")->implode("\n");
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('large.md', $body)]);
    $retrieval = app(RetrievalService::class);
    $hits = $retrieval->search('Summarize this document', KnowledgeCorpus::workspace($workspace->id));
    expect($hits)->toHaveCount(24)->and($hits->first()['chunk']->heading)->toBe('Section 1')
        ->and($hits->last()['chunk']->heading)->toBe('Section 40')
        ->and($retrieval->overviewIsPartial('Summarize this document', KnowledgeCorpus::workspace($workspace->id), $hits))->toBeTrue();
    WorkspaceChatAgent::fake(['Section 1 contains rehearsal rule 1.'."\nCITES: ".$hits->first()['chunk']->id]);
    $answer = app(WorkspaceChatService::class)->ask($workspace, 'Summarize this document', WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($answer->body)->toContain(ChatSummaryQuery::PARTIAL_NOTICE);
});

test('Harbor overviews cannot read private or unpublished documents', function () {
    $base = ['category' => 'general', 'body' => 'Policy text.', 'is_seeded' => true, 'is_published' => true];
    $public = KnowledgeArticle::query()->create($base + ['title' => 'Store rules', 'slug' => 'store-rules', 'workspace_id' => null]);
    $public->chunks()->create(['body' => 'Store rules allow returns within 30 days.', 'token_count' => 8]);
    $private = KnowledgeArticle::query()->create($base + ['title' => 'Private', 'slug' => 'private-summary', 'workspace_id' => Workspace::factory()->create()->id]);
    $private->chunks()->create(['body' => 'Secret client instructions.', 'token_count' => 3]);
    $hidden = KnowledgeArticle::query()->create(array_replace($base, ['title' => 'Hidden', 'slug' => 'hidden-summary', 'workspace_id' => null, 'is_published' => false]));
    $hidden->chunks()->create(['body' => 'Hidden policy.', 'token_count' => 2]);
    $hits = app(RetrievalService::class)->search('Summarize the policies', KnowledgeCorpus::harbor());
    expect($hits->pluck('chunk.knowledge_article_id')->all())->toBe([$public->id]);
});

test('blank plain-text and image-only pdf files explain why no answer can use them', function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('blank.txt', " \n\t "),
        UploadedFile::fake()->createWithContent('scan.pdf', file_get_contents(base_path('tests/Fixtures/empty.pdf'))),
    ]);
    expect($workspace->documents()->where('original_name', 'blank.txt')->sole()->failure_reason)->toBe(WorkspaceCopy::BLANK)
        ->and($workspace->documents()->where('original_name', 'scan.pdf')->sole()->failure_reason)->toBe(WorkspaceCopy::NO_TEXT)
        ->and(app(RetrievalService::class)->search('Summarize all documents', KnowledgeCorpus::workspace($workspace->id)))->toBeEmpty();
    $answer = app(WorkspaceChatService::class)->ask($workspace, 'Summarize scan.pdf', WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($answer->body)->toContain('could not be read', WorkspaceCopy::NO_TEXT);
    $workspace->documents()->where('original_name', 'scan.pdf')->update(['status' => WorkspaceDocumentStatus::Queued]);
    $answer = app(WorkspaceChatService::class)->ask($workspace, 'Summarize scan.pdf', WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($answer->body)->toContain('still being prepared');
    $answer = app(WorkspaceChatService::class)->ask($workspace, 'Summarize missing.pdf', WorkspaceAnswerControls::fromWorkspace($workspace));
    expect($answer->body)->toContain('no uploaded file with that filename');
});
