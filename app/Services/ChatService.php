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
        return $this->conversationFor($session)->messages()->create([
            'role' => 'user',
            'body' => $question,
        ]);
    }

    public function startNewConversation(DemoSession $session): void
    {
        $this->interruptStream($session);
        $this->conversationFor($session)->messages()->delete();
    }

    public function interruptStream(DemoSession $session): void
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

        $this->recordUser($session, $question);

        return $this->replyToLatest($session, $onDelta, $generation);
    }

    public function replyToLatest(DemoSession $session, ?Closure $onDelta = null, ?int $generation = null): ChatMessage
    {
        $generation ??= $this->currentStreamGeneration($session);
        $conversation = $this->conversationFor($session);
        $cap = (int) config('supportflow.demo.chat_turn_cap');
        $userTurns = $conversation->messages()->where('role', 'user')->count();
        $question = (string) $conversation->messages()->where('role', 'user')->latest('id')->value('body');

        if ($userTurns > $cap) {
            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => 'This demo chat is capped at '.$cap.' questions. Submit a support ticket if you still need help.',
                'cited_chunk_ids' => [],
            ]);
        }

        if ($abandoned = $this->abandonInFlightIfNeeded($session, $generation)) {
            return $abandoned;
        }

        if (ChatInjectionGate::blocks($question)) {
            if ($onDelta) {
                $onDelta(ChatInjectionGate::REFUSAL);
            }

            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => ChatInjectionGate::REFUSAL,
                'cited_chunk_ids' => [],
            ]);
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
                return $conversation->messages()->create([
                    'role' => 'assistant',
                    'body' => implode(' ', [$window->body, $box->body, $label->body]),
                    'cited_chunk_ids' => [$window->id, $box->id, $label->id],
                ]);
            }
        }

        $handoff = app(HarborChatHandoff::class)->proposal($conversation->messages()->orderBy('id')->get());
        if ($handoff !== null && ! $this->returns->mentionsSampleOrder($question)) {
            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => $handoff['order'] === '' ? HarborChatHandoff::ASK_ORDER : HarborChatHandoff::REVIEW,
                'cited_chunk_ids' => [],
            ]);
        }

        $returnOrder = $this->returns->requestedOrder(
            $question,
            $conversation->messages()->where('role', 'assistant')->latest('id')->value('body'),
        );

        if ($returnOrder !== null) {
            $result = $this->returns->assess($session, $returnOrder, $question);
            $policyIds = in_array($result['status'], ['eligible', 'review', 'blocked'], true)
                ? KnowledgeChunk::query()->where('heading', 'Start a return in chat')
                    ->whereHas('article', fn ($article) => $article->where('slug', 'return-window')
                        ->whereNull('workspace_id')->where('is_published', true)->where('is_seeded', true))
                    ->pluck('id')->all()
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

            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => $result['message'],
                'cited_chunk_ids' => $policyIds,
            ]);
        }

        $min = (float) config('supportflow.retrieval.min_similarity');
        $limit = (int) config('supportflow.retrieval.limit');
        $previous = $this->previousSafeUserTurn($conversation);
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
            if ($abandoned = $this->abandonInFlightIfNeeded($session, $generation)) {
                $this->recorder->complete($run, payload: ['grounded' => false, 'stopped' => true], retrievedChunkIds: []);

                return $abandoned;
            }

            $this->recorder->complete($run, payload: ['grounded' => false], retrievedChunkIds: []);

            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => $refusal,
                'cited_chunk_ids' => [],
            ]);
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

        $stream->each(function (StreamEvent $event) use (&$raw, &$cancelled, &$usedOrderTool, &$heldProposal, $onDelta, $session, $generation, $case): bool {
            if ($this->abandonInFlightIfNeeded($session, $generation) !== null) {
                $cancelled = true;

                return false;
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

        $abandoned = $this->abandonInFlightIfNeeded($session, $generation);

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
        $refusalPrefix = 'I don’t have a documented answer';

        if (ChatInjectionGate::isRefusal($body)) {
            $this->recorder->complete($run, $streamed, ['grounded' => false], $chunkIds);

            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => $body !== '' ? $body : ChatInjectionGate::REFUSAL,
                'cited_chunk_ids' => [],
            ]);
        }

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

        if ($abandoned = $this->abandonInFlightIfNeeded($session, $generation)) {
            $this->recorder->complete($run, payload: ['grounded' => false, 'stopped' => true], retrievedChunkIds: $chunkIds);

            return $abandoned;
        }

        if ($grounded && ChatSummaryQuery::target($question) !== null && $this->retrieval->overviewIsPartial($question, KnowledgeCorpus::harbor(), $matches)) {
            $body .= "\n\n".ChatSummaryQuery::PARTIAL_NOTICE;
        }

        $this->recorder->complete($run, $streamed, ['grounded' => $grounded], $chunkIds);

        return $conversation->messages()->create([
            'role' => 'assistant',
            'body' => $body,
            'cited_chunk_ids' => $cited,
        ]);
    }

    private function previousSafeUserTurn(ChatConversation $conversation): ?string
    {
        $priors = $conversation->messages()
            ->where('role', 'user')
            ->latest('id')
            ->skip(1)
            ->get();

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
    private function generationWasStopped(DemoSession $session): bool
    {
        return $this->stopWasRequested($session) || connection_aborted() === 1;
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
    private function streamIsStale(DemoSession $session, int $generation): bool
    {
        return $this->currentStreamGeneration($session) !== $generation;
    }

    /**
     * @phpstan-impure
     */
    private function abandonInFlightIfNeeded(DemoSession $session, int $generation): ?ChatMessage
    {
        if ($this->streamIsStale($session, $generation)) {
            return $this->abandonedStreamMessage($session);
        }

        if ($this->generationWasStopped($session)) {
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
