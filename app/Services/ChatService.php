<?php

namespace App\Services;

use App\Ai\Agents\SupportChatStreamAgent;
use App\Enums\AiRunFeature;
use App\Models\AiRun;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeChunk;
use App\Models\ReplacementCase;
use App\Support\ChatAnswerCopy;
use App\Support\ChatCitationTrailer;
use App\Support\ChatFollowUpPassages;
use App\Support\ChatFollowUpQuery;
use App\Support\ChatInjectionGate;
use App\Support\ChatSummaryQuery;
use App\Support\CitedChunkIds;
use App\Support\DemoGuide;
use App\Support\HarborOrderIntent;
use App\Support\KnowledgeCorpus;
use App\Support\SupportingPassages;
use App\Support\UntrustedContent;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Throwable;

class ChatService
{
    private const int TEXT_STREAM_STATE_CHECK_INTERVAL_NS = 100_000_000;

    public function __construct(
        private readonly RetrievalService $retrieval,
        private readonly AiUsageRecorder $recorder,
        private readonly ReplacementCaseService $replacements,
        private readonly HarborReturns $returns,
    ) {}

    public function conversationFor(DemoSession $session): ChatConversation
    {
        return ChatConversation::query()->firstOrCreate([
            'demo_session_id' => $session->id,
        ]);
    }

    public function recordUser(DemoSession $session, string $question): ChatMessage
    {
        return $this->recordUserMessage($this->conversationFor($session), $question);
    }

    private function recordUserMessage(ChatConversation $conversation, string $question): ChatMessage
    {
        return $conversation->messages()->create([
            'role' => 'user',
            'body' => $question,
        ]);
    }

    public function startNewConversation(DemoSession $session): void
    {
        $conversation = $this->conversationFor($session);
        $this->withConversationLock($conversation, function () use ($session, $conversation): void {
            $this->invalidateStream($session);
            $conversation->messages()->delete();
        });
    }

    public function interruptStream(DemoSession $session): void
    {
        $this->withConversationLock($this->conversationFor($session), fn () => $this->invalidateStream($session));
    }

    private function invalidateStream(DemoSession $session): void
    {
        $this->requestStop($session);
        Cache::put(
            $this->streamGenerationKey($session),
            $this->currentStreamGeneration($session) + 1,
            now()->addMinutes(5),
        );
        $this->streamLock($session)->forceRelease();
    }

    public function requestStop(DemoSession $session): void
    {
        Cache::put($this->stopCacheKey($session), true, now()->addMinutes(2));
    }

    /**
     * @phpstan-impure
     */
    public function stopWasRequested(DemoSession $session): bool
    {
        return Cache::get($this->stopCacheKey($session)) === true;
    }

    public function clearStopRequest(DemoSession $session): void
    {
        Cache::forget($this->stopCacheKey($session));
    }

    public function streamLockKey(DemoSession $session): string
    {
        return 'chat-stream:'.$session->id;
    }

    public function streamLock(DemoSession $session, int $seconds = 120): Lock
    {
        return Cache::lock($this->streamLockKey($session), $seconds);
    }

    public function stopVisibleTurn(DemoSession $session, int $userMessageId, string $prefix): bool
    {
        $conversation = $this->conversationFor($session);

        return $this->withConversationLock($conversation, fn (): bool => $this->stopVisibleTurnInConversation($session, $conversation, $userMessageId, $prefix));
    }

    private function stopVisibleTurnInConversation(DemoSession $session, ChatConversation $conversation, int $userMessageId, string $prefix): bool
    {
        $latestUserId = $conversation->messages()->where('role', 'user')->latest('id')->value('id');
        if ($latestUserId !== $userMessageId) {
            return false;
        }

        $this->invalidateStream($session);
        $assistant = $conversation->messages()->where('id', '>', $userMessageId)->where('role', 'assistant')->first();
        if ($assistant?->is_stopped && $assistant->body !== 'Stopped.') {
            return true;
        }

        $attributes = ['role' => 'assistant', 'body' => $prefix !== '' ? $prefix : 'Stopped.', 'is_stopped' => true, 'cited_chunk_ids' => []];
        if ($assistant === null) {
            $conversation->messages()->create($attributes);
        } else {
            $assistant->update($attributes);
        }

        return true;
    }

    /**
     * @template Result
     *
     * @param  Closure(): Result  $operation
     * @return Result
     */
    private function withConversationLock(ChatConversation $conversation, Closure $operation): mixed
    {
        return $conversation->getConnection()->transaction(function () use ($conversation, $operation): mixed {
            $conversation->newQuery()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            return $operation();
        });
    }

    /**
     * @param  list<int>  $cited
     * @param  (Closure(bool): void)|null  $recordCompletion
     */
    private function saveAssistant(DemoSession $session, ChatConversation $conversation, int $generation, string $body, array $cited = [], ?Closure $recordCompletion = null): ChatMessage
    {
        return $this->withConversationLock($conversation, function () use ($session, $conversation, $generation, $body, $cited, $recordCompletion): ChatMessage {
            $abandoned = $this->abandonInFlightIfNeeded($session, $generation);
            $message = $abandoned ?? $conversation->messages()->create([
                'role' => 'assistant',
                'body' => $body,
                'cited_chunk_ids' => $cited,
            ]);
            if ($recordCompletion !== null) {
                $recordCompletion($abandoned === null);
            }

            return $message;
        });
    }

    public function recordStoppedIfOrphaned(DemoSession $session): ?ChatMessage
    {
        $conversation = $this->conversationFor($session);
        $latest = $conversation->messages()->latest('id')->first();

        if ($latest === null || $latest->role !== 'user') {
            return null;
        }

        return $conversation->messages()->create([
            'role' => 'assistant',
            'body' => 'Stopped.',
            'is_stopped' => true,
            'cited_chunk_ids' => [],
        ]);
    }

    public function ask(DemoSession $session, string $question, ?Closure $onDelta = null): ChatMessage
    {
        $generation = $this->currentStreamGeneration($session);
        $conversation = $this->conversationFor($session);

        $userMessage = $this->recordUserMessage($conversation, $question);

        return $this->replyInConversation(
            $session,
            $conversation,
            $generation,
            $onDelta === null ? null : fn (string $visible) => $onDelta($visible, $userMessage->id),
        );
    }

    public function replyToLatest(DemoSession $session, ?Closure $onDelta = null, ?int $generation = null): ChatMessage
    {
        $generation ??= $this->currentStreamGeneration($session);

        return $this->replyInConversation($session, $this->conversationFor($session), $generation, $onDelta);
    }

    private function replyInConversation(DemoSession $session, ChatConversation $conversation, int $generation, ?Closure $onDelta): ChatMessage
    {
        $cap = (int) config('supportflow.demo.chat_turn_cap');
        $latestUserTurn = $conversation->messages()
            ->where('role', 'user')
            ->select('body')
            ->selectRaw('count(*) over () as user_turns')
            ->latest('id')
            ->first();
        $userTurns = (int) ($latestUserTurn?->getAttribute('user_turns') ?? 0);
        $question = (string) ($latestUserTurn->body ?? '');

        if ($userTurns > $cap) {
            return $this->saveAssistant($session, $conversation, $generation, 'This demo chat is capped at '.$cap.' questions. Submit a support ticket if you still need help.');
        }

        if ($abandoned = $this->abandonInFlightIfNeeded($session, $generation)) {
            return $abandoned;
        }

        if (ChatInjectionGate::blocks($question)) {
            if ($onDelta) {
                $onDelta(ChatInjectionGate::REFUSAL);
            }

            return $this->saveAssistant($session, $conversation, $generation, ChatInjectionGate::REFUSAL);
        }

        if ($question === DemoGuide::prompt('return_window')['question']) {
            $passages = KnowledgeChunk::query()
                ->whereHas('article', fn ($article) => $article->where('slug', 'return-window')
                    ->whereNull('workspace_id')->where('is_published', true)->where('is_seeded', true))
                ->get();
            $window = $passages->first(fn (KnowledgeChunk $chunk): bool => str_contains($chunk->body, 'within 30 days of delivery with tags attached'));
            $box = $passages->firstWhere('heading', 'Box not required');
            $label = $passages->firstWhere('heading', 'Prepaid labels');

            if ($window !== null && $box !== null && $label !== null) {
                return $this->saveAssistant(
                    $session,
                    $conversation,
                    $generation,
                    implode(' ', [$window->body, $box->body, $label->body]),
                    [$window->id, $box->id, $label->id],
                );
            }
        }

        $history = $conversation->messages()->orderBy('id')->get();
        $handoff = app(HarborChatHandoff::class)->proposal($history);
        if ($handoff !== null && ! $this->returns->mentionsSampleOrder($question)) {
            return $this->saveAssistant(
                $session,
                $conversation,
                $generation,
                $handoff['order'] === '' ? $this->returns->orderPrompt($question) : HarborChatHandoff::REVIEW,
            );
        }

        $returnOrder = $this->returns->requestedOrder(
            $question,
            $history->last(fn (ChatMessage $message): bool => $message->role === 'assistant')?->body,
        );

        if ($returnOrder !== null) {
            $result = $this->returns->assess($session, $returnOrder, $question);
            $policyIds = in_array($result['status'], ['eligible', 'review', 'blocked'], true)
                ? KnowledgeChunk::query()->where('heading', 'Start a return in chat')
                    ->whereHas('article', fn ($article) => $article->where('slug', 'return-window')
                        ->whereNull('workspace_id')->where('is_published', true)->where('is_seeded', true))
                    ->get(['id'])->map(fn (KnowledgeChunk $chunk): int => $chunk->id)->all()
                : [];

            if ($this->returns->asksAboutPackagingAndReturn($question)) {
                $box = KnowledgeChunk::query()->where('heading', 'Box not required')
                    ->whereHas('article', fn ($article) => $article->where('slug', 'return-window')
                        ->whereNull('workspace_id')->where('is_published', true)->where('is_seeded', true))
                    ->first();

                if ($box !== null) {
                    $result['message'] = $box->body."\n\n".$result['message'];
                    array_unshift($policyIds, $box->id);
                }
            }

            return $this->saveAssistant($session, $conversation, $generation, $result['message'], array_values($policyIds));
        }

        $min = (float) config('supportflow.retrieval.min_similarity');
        $limit = (int) config('supportflow.retrieval.limit');
        $previous = $this->previousSafeUserTurn($history);
        unset($history);
        $query = ChatFollowUpQuery::retrievalQuery($question, $previous);
        $corpus = KnowledgeCorpus::harbor();
        $matches = $this->retrieval->search($query, $corpus, $limit, $min);

        if ($matches->isEmpty() && $previous !== null && $query === $question) {
            $matches = $this->retrieval->search($previous."\n".$question, $corpus, $limit, $min);
        }

        $contextual = $previous !== null && ChatFollowUpQuery::needsPreviousSubjects($question, $previous);
        [$matches, $continuing] = ChatFollowUpPassages::mergePreviousCitations(
            $matches,
            $conversation,
            $corpus,
            $limit,
            $contextual,
        );

        $chunkIds = array_values($matches->map(fn (array $row): int => $row['chunk']->id)->all());
        $case = HarborOrderIntent::matches($question) && ! $this->returns->mentionsSampleOrder($question)
            ? $this->replacements->forSession($session)
            : null;

        if ($abandoned = $this->abandonInFlightIfNeeded($session, $generation)) {
            return $abandoned;
        }

        $run = $this->recorder->start(
            AiRunFeature::Chat,
            null,
            (string) config('supportflow.models.chat'),
        );

        try {
            return $this->finishHarborReply($session, $conversation, $question, $matches, $chunkIds, $continuing, $generation, $onDelta, $run, $case);
        } catch (Throwable $exception) {
            $this->recorder->fail($run, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $matches
     * @param  list<int>  $chunkIds
     */
    private function finishHarborReply(
        DemoSession $session,
        ChatConversation $conversation,
        string $question,
        Collection $matches,
        array $chunkIds,
        bool $continuing,
        int $generation,
        ?Closure $onDelta,
        AiRun $run,
        ?ReplacementCase $case = null,
    ): ChatMessage {
        $refusal = 'I don’t have a documented answer in the Harbor Outfitters knowledge base. Select Create a support ticket to send your question to Agent Desk for review.';

        if ($matches->isEmpty() && $case === null) {
            return $this->saveAssistant($session, $conversation, $generation, $refusal, recordCompletion: function (bool $saved) use ($run): void {
                $this->recorder->complete($run, payload: $saved ? ['grounded' => false] : ['grounded' => false, 'stopped' => true]);
            });
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

        $allowed = implode(', ', $chunkIds);
        $raw = '';
        $cancelled = false;

        $continuation = $continuing ? ChatFollowUpPassages::CONTINUATION."\n" : '';
        $orderNote = $case === null
            ? ''
            : "Order tools are available for this turn. Fictional order, stock, and replacement facts must come from those tools. Policy facts must still come only from the passages.\n";
        $passageNote = $matches->isEmpty()
            ? "No policy passages matched this question. Do not invent a policy.\n"
            : "Answer only from these passages. {$continuation}CITES IDs must be a subset of: {$allowed}.\n\n";
        $passageBlock = $matches->isEmpty() ? '' : "\n\nPassages:\n{$passages}";
        $agent = $case === null
            ? SupportChatStreamAgent::make(conversation: $conversation)
            : SupportChatStreamAgent::make(
                conversation: $conversation,
                replacementCaseId: $case->id,
                replacementGeneration: $case->generation,
            );
        $usedOrderTool = false;
        $heldProposal = false;

        $stream = $agent->stream(
            $orderNote.$passageNote."Question:\n"
            .UntrustedContent::wrap('chat_user', $question)
            .$passageBlock,
            provider: Lab::OpenAI,
            model: (string) config('supportflow.models.chat'),
        );

        $nextTextStateCheckAt = 0;
        $stream->each(function (StreamEvent $event) use (&$raw, &$cancelled, &$usedOrderTool, &$heldProposal, &$nextTextStateCheckAt, $onDelta, $session, $generation, $case): bool {
            $now = hrtime(true);
            if ($case !== null || ! ($event instanceof TextDelta) || $now >= $nextTextStateCheckAt || connection_aborted() === 1) {
                if ($this->abandonInFlightIfNeeded($session, $generation) !== null) {
                    $cancelled = true;

                    return false;
                }

                if ($event instanceof TextDelta) {
                    $nextTextStateCheckAt = $now + self::TEXT_STREAM_STATE_CHECK_INTERVAL_NS;
                }
            }

            if ($event instanceof ToolCall || $event instanceof ToolResult || $event instanceof ToolApprovalRequest) {
                $usedOrderTool = true;
            }

            if ($event instanceof ToolApprovalRequest && $case instanceof ReplacementCase) {
                foreach ($event->pendingApprovals as $approval) {
                    if ($approval instanceof PendingApproval && $this->replacements->holdProposal($case->id, $case->generation, $approval)) {
                        $heldProposal = true;
                    }
                }
            }

            if ($event instanceof TextDelta) {
                $raw .= $event->delta;
                if ($onDelta) {
                    $onDelta(ChatCitationTrailer::visible($raw));
                }
            }

            return true;
        });

        $abandoned = $cancelled || $case !== null
            ? $this->abandonInFlightIfNeeded($session, $generation)
            : null;

        if ($cancelled || $abandoned !== null) {
            $this->recorder->complete($run, payload: ['grounded' => false, 'stopped' => true], retrievedChunkIds: $chunkIds);

            return $abandoned ?? $this->stoppedAssistantMessage($session);
        }

        $streamed = null;
        $stream->then(function (StreamedAgentResponse $response) use (&$streamed): void {
            $streamed = $response;
        });

        $text = $streamed instanceof StreamedAgentResponse ? $streamed->text : $raw;
        [$body, $citedRaw] = ChatCitationTrailer::split($text);
        $body = ChatAnswerCopy::normalize($body);
        if (ChatInjectionGate::isRefusal($body)) {
            $grounded = false;
            $cited = [];
        } else {
            $refusalPrefix = 'I don’t have a documented answer';

            if ($heldProposal) {
                $body = 'I’ve prepared a demo replacement request. Select Confirm to save it or Cancel to dismiss it. Nothing will be shipped.';
            } elseif (! str_starts_with($body, $refusalPrefix)) {
                $body = SupportingPassages::includeAskedFacets($body, $question, $matches);
            }

            $cited = $heldProposal || str_starts_with($body, $refusalPrefix)
                ? []
                : CitedChunkIds::usedInBody(
                    $body,
                    $matches,
                    CitedChunkIds::onlyAllowed($citedRaw, $chunkIds),
                );
            $grounded = $cited !== [] && $body !== '';
            $orderTurn = $usedOrderTool || $heldProposal;

            if (! $grounded && ! $orderTurn) {
                $body = $refusal;
                $cited = [];
            }

            if ($orderTurn && trim($body) === '') {
                $body = 'Nothing was saved.';
                $cited = [];
                $grounded = false;
            }

            if ($grounded && ChatSummaryQuery::target($question) !== null && $this->retrieval->overviewIsPartial($question, KnowledgeCorpus::harbor(), $matches)) {
                $body .= "\n\n".ChatSummaryQuery::PARTIAL_NOTICE;
            }
        }

        return $this->saveAssistant($session, $conversation, $generation, $body, $cited, function (bool $saved) use ($run, $streamed, $grounded, $chunkIds): void {
            $this->recorder->complete(
                $run,
                $saved ? $streamed : null,
                $saved ? ['grounded' => $grounded] : ['grounded' => false, 'stopped' => true],
                $chunkIds,
            );
        });
    }

    /**
     * @param  Collection<int, ChatMessage>  $history
     */
    private function previousSafeUserTurn(Collection $history): ?string
    {
        $priors = $history->where('role', 'user')->reverse()->skip(1);

        foreach ($priors as $message) {
            if (! ChatInjectionGate::blocks($message->body)) {
                return $message->body;
            }
        }

        return null;
    }

    /**
     * @phpstan-impure
     */
    private function currentStreamGeneration(DemoSession $session): int
    {
        return (int) Cache::get($this->streamGenerationKey($session), 0);
    }

    /**
     * @phpstan-impure
     */
    private function abandonInFlightIfNeeded(DemoSession $session, int $generation): ?ChatMessage
    {
        $generationKey = $this->streamGenerationKey($session);
        $stopKey = $this->stopCacheKey($session);
        $state = Cache::many([$generationKey, $stopKey]);

        if ((int) ($state[$generationKey] ?? 0) !== $generation) {
            return $this->abandonedStreamMessage($session);
        }

        if (($state[$stopKey] ?? null) === true || connection_aborted() === 1) {
            return $this->stoppedAssistantMessage($session);
        }

        return null;
    }

    private function abandonedStreamMessage(DemoSession $session): ChatMessage
    {
        $latest = $this->conversationFor($session)->messages()->latest('id')->first();

        if ($latest instanceof ChatMessage) {
            return $latest;
        }

        return $this->conversationFor($session)->messages()->make([
            'role' => 'assistant',
            'body' => 'Stopped.',
            'is_stopped' => true,
            'cited_chunk_ids' => [],
        ]);
    }

    private function stoppedAssistantMessage(DemoSession $session): ChatMessage
    {
        return $this->recordStoppedIfOrphaned($session)
            ?? $this->conversationFor($session)->messages()->latest('id')->firstOrFail();
    }

    private function stopCacheKey(DemoSession $session): string
    {
        return 'chat-stop:'.$session->id;
    }

    private function streamGenerationKey(DemoSession $session): string
    {
        return 'chat-gen:'.$session->id;
    }
}
