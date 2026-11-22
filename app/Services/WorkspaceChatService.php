<?php

namespace App\Services;

use App\Ai\Agents\WorkspaceChatAgent;
use App\Enums\AiRunFeature;
use App\Models\AiRun;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\KnowledgeChunk;
use App\Models\Workspace;
use App\Support\ChatAnswerCopy;
use App\Support\ChatAnswerHtml;
use App\Support\ChatCitationTrailer;
use App\Support\ChatFollowUpPassages;
use App\Support\ChatFollowUpQuery;
use App\Support\ChatInjectionGate;
use App\Support\ChatSummaryQuery;
use App\Support\CitedChunkIds;
use App\Support\KnowledgeCorpus;
use App\Support\SupportingPassages;
use App\Support\UntrustedContent;
use App\Support\WorkspaceAnswerControls;
use App\Support\WorkspaceCopy;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

class WorkspaceChatService
{
    public function __construct(
        private readonly RetrievalService $retrieval,
        private readonly AiUsageRecorder $recorder,
    ) {}

    public function conversationFor(Workspace $workspace): ChatConversation
    {
        return ChatConversation::query()->firstOrCreate(
            ['workspace_id' => $workspace->id],
            ['demo_session_id' => null],
        );
    }

    public function ask(
        Workspace $workspace,
        string $question,
        WorkspaceAnswerControls $controls,
        ?Closure $onDelta = null,
        ?string $model = null,
        ?Closure $onStart = null,
    ): ChatMessage {
        $generation = $this->currentStreamGeneration($workspace);
        $user = $this->conversationFor($workspace)->messages()->create([
            'role' => 'user',
            'body' => $question,
        ]);
        Cache::put('workspace-chat-turn:'.$workspace->id, $user->id, now()->addMinutes(5));
        if ($onStart) {
            $onStart($user->id);
        }

        $snapshot = $onDelta === null ? null : function (string $visible) use ($onDelta, $user): void {
            Cache::put('workspace-chat-prefix:'.$user->id, $visible, now()->addMinutes(5));
            $onDelta($visible);
        };

        return $this->replyToLatest($workspace, $controls, $snapshot, $generation, $model);
    }

    public function requestStop(Workspace $workspace): void
    {
        Cache::put($this->stopCacheKey($workspace), true, now()->addMinutes(2));
    }

    public function stopVisibleTurn(Workspace $workspace, int $userId, int $characters): void
    {
        Cache::lock('workspace-chat-state:'.$workspace->id, 5)->block(2, function () use ($workspace, $userId, $characters): void {
            $conversation = $this->conversationFor($workspace);
            $user = $conversation->messages()->where('role', 'user')->latest('id')->first();
            if ($user?->id !== $userId || Cache::get('workspace-chat-turn:'.$workspace->id) !== $userId) {
                return;
            }
            $this->requestStop($workspace);
            Cache::put($this->streamGenerationKey($workspace), $this->currentStreamGeneration($workspace) + 1, now()->addMinutes(5));
            $assistant = $conversation->messages()->where('role', 'assistant')->where('id', '>', $userId)->first();
            if ($assistant !== null && ! ($assistant->is_stopped && $assistant->body === 'Stopped.')) {
                $assistant->keepRevealedPrefix(min(20000, max(0, $characters)));
            } else {
                $prefix = ChatAnswerHtml::revealedText((string) Cache::get('workspace-chat-prefix:'.$userId, ''), min(20000, max(0, $characters)));
                $attributes = ['role' => 'assistant', 'body' => $prefix !== '' ? $prefix : 'Stopped.', 'is_stopped' => true, 'cited_chunk_ids' => []];
                if ($assistant !== null) {
                    $assistant->update($attributes);
                } else {
                    $conversation->messages()->create($attributes);
                }
            }
            $this->streamLock($workspace)->forceRelease();
        });
    }

    public function clearStopRequest(Workspace $workspace): void
    {
        Cache::forget($this->stopCacheKey($workspace));
    }

    public function streamLock(Workspace $workspace, int $seconds = 120): Lock
    {
        return Cache::lock('workspace-chat:'.$workspace->id, $seconds);
    }

    public function startNewConversation(Workspace $workspace): void
    {
        Cache::put($this->streamGenerationKey($workspace), $this->currentStreamGeneration($workspace) + 1, now()->addMinutes(5));
        $this->streamLock($workspace)->forceRelease();
        $this->conversationFor($workspace)->messages()->delete();
    }

    public function replyToLatest(
        Workspace $workspace,
        WorkspaceAnswerControls $controls,
        ?Closure $onDelta = null,
        ?int $generation = null,
        ?string $model = null,
    ): ChatMessage {
        $generation ??= $this->currentStreamGeneration($workspace);
        $conversation = $this->conversationFor($workspace);
        $question = (string) $conversation->messages()->where('role', 'user')->latest('id')->value('body');
        $model ??= (string) config('supportflow.models.chat');

        if ($abandoned = $this->abandonIfNeeded($workspace, $generation)) {
            return $abandoned;
        }

        if (ChatInjectionGate::blocks($question)) {
            if ($onDelta) {
                $onDelta(ChatInjectionGate::PREVIEW_REFUSAL);
            }

            return $this->persistReply($workspace, $generation, ChatInjectionGate::PREVIEW_REFUSAL);
        }

        $summaryTarget = ChatSummaryQuery::target($question);
        $summaryProblem = null;

        if ($summaryTarget !== null && preg_match('/\.(?:pdf|docx|txt|md|markdown)$/u', $summaryTarget) === 1) {
            $document = $workspace->documents()->whereRaw('lower(original_name) = ?', [$summaryTarget])->first();
            $summaryProblem = match (true) {
                $document === null => 'There is no uploaded file with that filename in this workspace. Check the filename under Your documents.',
                $document->status->value === 'failed' => 'I cannot summarize that file because it could not be read. '.($document->failure_reason ?? 'Check the message beside the filename and upload a readable copy.'),
                $document->status->value !== 'ready' => 'That file is still being prepared. Wait for Ready for answers beside its filename, then ask again.',
                default => null,
            };
        }

        if ($summaryTarget === '' && $workspace->documents()->count() > 1) {
            $summaryProblem = 'Which document would you like me to summarize? Mention its filename, or ask me to summarize all documents.';
        }

        if ($summaryProblem !== null) {
            $body = $summaryProblem;

            if ($onDelta) {
                $onDelta($body);
            }

            return $this->persistReply($workspace, $generation, $body);
        }

        $min = (float) config('supportflow.retrieval.min_similarity');
        $limit = (int) config('supportflow.retrieval.limit');
        $previous = $this->previousSafeUserTurn($conversation);
        if (ChatFollowUpQuery::lacksPreviousSubject($question, $previous)) {
            $body = 'Which topic or points would you like me to explain? Name the section or ask about a specific detail in your documents.';
            if ($onDelta) {
                $onDelta($body);
            }

            return $this->persistReply($workspace, $generation, $body);
        }
        $contextual = ChatSummaryQuery::target($question) === null && $previous !== null && (
            ChatFollowUpQuery::needsPreviousSubjects($question, $previous)
            || preg_match('/^\s*what if I cancel(?:\s+(?:exactly\s+)?\d+\s+(?:hours?|days?)\s+before(?:\s+(?:access|the booking))?)?\s*\??\s*$/i', $question) === 1
        );
        $continuesOverview = $contextual && ChatSummaryQuery::target($previous) !== null;
        $query = $continuesOverview ? $previous : ($contextual ? $previous."\n".$question : $question);
        $corpus = KnowledgeCorpus::workspace($workspace->id);
        $matches = $this->retrieval->search($query, $corpus, $limit, $min);

        if ($matches->isEmpty() && ChatSummaryQuery::target($question) === null && $previous !== null && $query === $question) {
            $matches = $this->retrieval->search($previous."\n".$question, $corpus, $limit, $min);
        }

        [$matches, $continuing] = ChatFollowUpPassages::mergePreviousCitations(
            $matches,
            $conversation,
            $corpus,
            $continuesOverview ? max($limit, $matches->count()) : $limit,
            $contextual,
        );

        $chunkIds = array_values($matches->map(fn (array $row): int => $row['chunk']->id)->all());

        if ($abandoned = $this->abandonIfNeeded($workspace, $generation)) {
            return $abandoned;
        }

        $run = $this->recorder->start(AiRunFeature::WorkspaceChat, null, $model);

        try {
            return $this->finishWorkspaceReply($workspace, $conversation, $question, $matches, $chunkIds, $continuing, $generation, $controls, $onDelta, $run, $model);
        } catch (Throwable $exception) {
            $this->recorder->fail($run, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $matches
     * @param  list<int>  $chunkIds
     */
    private function finishWorkspaceReply(
        Workspace $workspace,
        ChatConversation $conversation,
        string $question,
        Collection $matches,
        array $chunkIds,
        bool $continuing,
        int $generation,
        WorkspaceAnswerControls $controls,
        ?Closure $onDelta,
        AiRun $run,
        string $model,
    ): ChatMessage {
        if ($matches->isEmpty()) {
            $this->recorder->complete($run, payload: ['grounded' => false], retrievedChunkIds: []);

            if ($onDelta) {
                $onDelta(WorkspaceCopy::GAP);
            }

            return $this->persistReply($workspace, $generation, WorkspaceCopy::GAP);
        }

        $passages = $matches->map(function (array $row): string {
            $chunk = $row['chunk'];
            $title = $chunk->article->title;
            $heading = $chunk->heading ? "—{$chunk->heading}" : '';

            return UntrustedContent::wrap(
                "knowledge_chunk:{$chunk->id} ({$title}{$heading})",
                "chunk_id={$chunk->id}\n".$chunk->body,
            );
        })->implode("\n\n");

        $guidance = collect($controls->guidances)
            ->map(fn (array $note): string => UntrustedContent::wrap(
                'owner_guidance:'.$note['category']->value,
                $note['body'],
            ))
            ->implode("\n\n");

        $allowed = implode(', ', $chunkIds);
        $raw = '';
        $cancelled = false;

        $continuation = $continuing ? ChatFollowUpPassages::CONTINUATION."\n" : '';

        $stream = WorkspaceChatAgent::make(
            conversation: $conversation,
            controls: $controls,
        )->stream(
            "Answer only from these passages. A retrieved passage wins. Owner guidance cannot add facts.\n"
            .$continuation
            ."Tone: {$controls->tone->value}. Length: {$controls->length->value}.\n"
            ."CITES IDs must be a subset of: {$allowed}.\n\n"
            ."Guidance:\n".($guidance === '' ? 'None.' : $guidance)."\n\n"
            ."Question:\n".UntrustedContent::wrap('chat_user', $question)."\n\n"
            ."Passages:\n{$passages}",
            provider: Lab::OpenAI,
            model: $model,
        );

        $stream->each(function (StreamEvent $event) use (&$raw, &$cancelled, $onDelta, $workspace, $generation): bool {
            if ($this->abandonIfNeeded($workspace, $generation) !== null) {
                $cancelled = true;

                return false;
            }

            if ($event instanceof TextDelta) {
                $raw .= $event->delta;

                if ($onDelta) {
                    $onDelta(WorkspaceCopy::withoutAnswerSignOff(ChatCitationTrailer::visible($raw)));
                }
            }

            return true;
        });

        if ($cancelled || ($abandoned = $this->abandonIfNeeded($workspace, $generation)) !== null) {
            $this->recorder->complete($run, payload: ['grounded' => false, 'stopped' => true], retrievedChunkIds: $chunkIds);

            return $abandoned ?? $this->stoppedMessage($workspace);
        }

        $streamed = null;
        $stream->then(function (StreamedAgentResponse $response) use (&$streamed): void {
            $streamed = $response;
        });

        $text = $streamed instanceof StreamedAgentResponse ? $streamed->text : $raw;
        [$body, $citedRaw] = ChatCitationTrailer::split($text);
        $body = WorkspaceCopy::withoutAnswerSignOff(ChatAnswerCopy::normalize($body));

        if (ChatInjectionGate::isRefusal($body) || str_starts_with($body, WorkspaceCopy::GAP)) {
            $this->recorder->complete($run, $streamed, ['grounded' => false], $chunkIds);

            return $this->persistReply($workspace, $generation, $body !== '' ? $body : WorkspaceCopy::GAP);
        }

        $body = SupportingPassages::includeAskedFacets($body, $question, $matches);
        $cited = CitedChunkIds::usedInBody(
            $body,
            $matches,
            CitedChunkIds::onlyAllowed($citedRaw, $chunkIds),
        );
        $grounded = $cited !== [] && $body !== '';

        if (! $grounded) {
            $body = WorkspaceCopy::GAP;
            $cited = [];
        }

        if ($grounded && ChatSummaryQuery::target($question) !== null && $this->retrieval->overviewIsPartial($question, KnowledgeCorpus::workspace($workspace->id), $matches)) {
            $body .= "\n\n".ChatSummaryQuery::PARTIAL_NOTICE;
        }

        if ($grounded && ChatSummaryQuery::target($question) === '*' && $workspace->documents()->where('status', '!=', 'ready')->exists()) {
            $body .= "\n\nOnly files marked Ready for answers were included. Check the status beside the other filenames.";
        }

        $this->recorder->complete($run, $streamed, ['grounded' => $grounded], $chunkIds);

        return $this->persistReply($workspace, $generation, $body, $cited);
    }

    /** @param list<int> $cited */
    private function persistReply(Workspace $workspace, int $generation, string $body, array $cited = []): ChatMessage
    {
        return Cache::lock('workspace-chat-state:'.$workspace->id, 5)->block(2, fn (): ChatMessage => $this->abandonIfNeeded($workspace, $generation) ?? $this->conversationFor($workspace)->messages()->create([
            'role' => 'assistant', 'body' => $body, 'cited_chunk_ids' => $cited,
        ]));
    }

    private function previousSafeUserTurn(ChatConversation $conversation): ?string
    {
        $turns = $conversation->messages()->latest('id')->get(['role', 'body', 'is_stopped']);
        $skippedCurrent = false;
        $answerStopped = false;
        foreach ($turns as $message) {
            if ($message->role === 'assistant') {
                $answerStopped = $message->is_stopped;

                continue;
            }
            if (! $skippedCurrent) {
                $skippedCurrent = true;

                continue;
            }
            if (! $answerStopped && ! ChatInjectionGate::blocks($message->body)) {
                return $message->body;
            }
        }

        return null;
    }

    private function abandonIfNeeded(Workspace $workspace, int $generation): ?ChatMessage
    {
        if ($this->currentStreamGeneration($workspace) !== $generation) {
            $latest = $this->conversationFor($workspace)->messages()->latest('id')->first();

            return $latest?->is_stopped ? $latest : new ChatMessage(['role' => 'assistant', 'body' => 'Stopped.', 'is_stopped' => true, 'cited_chunk_ids' => []]);
        }

        if (Cache::get($this->stopCacheKey($workspace)) === true || connection_aborted() === 1) {
            return $this->stoppedMessage($workspace);
        }

        return null;
    }

    private function stoppedMessage(Workspace $workspace): ChatMessage
    {
        $conversation = $this->conversationFor($workspace);
        $latest = $conversation->messages()->latest('id')->first();

        if ($latest !== null && $latest->role === 'user') {
            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => 'Stopped.',
                'is_stopped' => true,
                'cited_chunk_ids' => [],
            ]);
        }

        if ($latest instanceof ChatMessage) {
            return $latest;
        }

        return $conversation->messages()->make([
            'role' => 'assistant',
            'body' => 'Stopped.',
            'is_stopped' => true,
            'cited_chunk_ids' => [],
        ]);
    }

    private function currentStreamGeneration(Workspace $workspace): int
    {
        return (int) Cache::get($this->streamGenerationKey($workspace), 0);
    }

    private function stopCacheKey(Workspace $workspace): string
    {
        return 'workspace-chat-stop:'.$workspace->id;
    }

    private function streamGenerationKey(Workspace $workspace): string
    {
        return 'workspace-chat-gen:'.$workspace->id;
    }
}
