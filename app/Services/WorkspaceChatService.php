<?php

namespace App\Services;

use App\Ai\Agents\WorkspaceChatAgent;
use App\Enums\AiRunFeature;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Workspace;
use App\Support\ChatAnswerCopy;
use App\Support\ChatCitationTrailer;
use App\Support\ChatFollowUpPassages;
use App\Support\ChatFollowUpQuery;
use App\Support\ChatInjectionGate;
use App\Support\CitedChunkIds;
use App\Support\KnowledgeCorpus;
use App\Support\SupportingPassages;
use App\Support\UntrustedContent;
use App\Support\WorkspaceAnswerControls;
use App\Support\WorkspaceCopy;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\TextDelta;

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
    ): ChatMessage {
        $generation = $this->currentStreamGeneration($workspace);
        $this->conversationFor($workspace)->messages()->create([
            'role' => 'user',
            'body' => $question,
        ]);

        return $this->replyToLatest($workspace, $controls, $onDelta, $generation, $model);
    }

    public function requestStop(Workspace $workspace): void
    {
        Cache::put($this->stopCacheKey($workspace), true, now()->addMinutes(2));
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

            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => ChatInjectionGate::PREVIEW_REFUSAL,
                'cited_chunk_ids' => [],
            ]);
        }

        $min = (float) config('supportflow.retrieval.min_similarity');
        $limit = (int) config('supportflow.retrieval.limit');
        $previous = $this->previousSafeUserTurn($conversation);
        $query = ChatFollowUpQuery::retrievalQuery($question, $previous);
        $corpus = KnowledgeCorpus::workspace($workspace->id);
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

        if ($abandoned = $this->abandonIfNeeded($workspace, $generation)) {
            return $abandoned;
        }

        $run = $this->recorder->start(AiRunFeature::WorkspaceChat, null, $model);

        if ($matches->isEmpty()) {
            $this->recorder->complete($run, payload: ['grounded' => false], retrievedChunkIds: []);

            if ($onDelta) {
                $onDelta(WorkspaceCopy::GAP);
            }

            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => WorkspaceCopy::GAP,
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
                    $onDelta(ChatCitationTrailer::visible($raw));
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
        $body = ChatAnswerCopy::normalize($body);

        if (ChatInjectionGate::isRefusal($body) || str_starts_with($body, WorkspaceCopy::GAP)) {
            $this->recorder->complete($run, $streamed, ['grounded' => false], $chunkIds);

            return $conversation->messages()->create([
                'role' => 'assistant',
                'body' => $body !== '' ? $body : WorkspaceCopy::GAP,
                'cited_chunk_ids' => [],
            ]);
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

    private function abandonIfNeeded(Workspace $workspace, int $generation): ?ChatMessage
    {
        if ($this->currentStreamGeneration($workspace) !== $generation || Cache::get($this->stopCacheKey($workspace)) === true || connection_aborted() === 1) {
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
                'cited_chunk_ids' => [],
            ]);
        }

        if ($latest instanceof ChatMessage) {
            return $latest;
        }

        return $conversation->messages()->make([
            'role' => 'assistant',
            'body' => 'Stopped.',
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
