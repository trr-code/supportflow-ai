<?php

namespace App\Services;

use App\Ai\Agents\SupportChatStreamAgent;
use App\Enums\AiRunFeature;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Support\ChatAnswerCopy;
use App\Support\ChatCitationTrailer;
use App\Support\ChatInjectionGate;
use App\Support\CitedChunkIds;
use App\Support\SupportingPassages;
use App\Support\UntrustedContent;
use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\TextDelta;

class ChatService
{
    public function __construct(
        private RetrievalService $retrieval,
        private AiUsageRecorder $recorder,
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
        $this->clearStopRequest($session);
        $this->conversationFor($session)->messages()->delete();
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
            'cited_chunk_ids' => [],
        ]);
    }

    public function ask(DemoSession $session, string $question, ?Closure $onDelta = null): ChatMessage
    {
        $this->recordUser($session, $question);

        return $this->replyToLatest($session, $onDelta);
    }

    public function replyToLatest(DemoSession $session, ?Closure $onDelta = null): ChatMessage
    {
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

        if ($this->generationWasStopped($session)) {
            return $this->stoppedAssistantMessage($session);
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

        $min = (float) config('supportflow.retrieval.min_similarity');
        $matches = $this->retrieval->search($question, (int) config('supportflow.retrieval.limit'), $min);
        $chunkIds = array_values($matches->map(fn (array $row): int => $row['chunk']->id)->all());

        if ($this->generationWasStopped($session)) {
            return $this->stoppedAssistantMessage($session);
        }

        $run = $this->recorder->start(
            AiRunFeature::Chat,
            null,
            (string) config('supportflow.models.chat'),
        );

        $refusal = 'I don’t have a documented answer in the Harbor & Co knowledge base. Submit a support ticket and a human agent will take it from here.';

        if ($matches->isEmpty()) {
            if ($this->generationWasStopped($session)) {
                $this->recorder->complete($run, payload: ['grounded' => false, 'stopped' => true], retrievedChunkIds: []);

                return $this->stoppedAssistantMessage($session);
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

        $stream = SupportChatStreamAgent::make()->stream(
            "Answer only from these passages. CITES IDs must be a subset of: {$allowed}.\n\nQuestion:\n"
            .UntrustedContent::wrap('chat_user', $question)
            ."\n\nPassages:\n{$passages}",
            provider: Lab::OpenAI,
            model: (string) config('supportflow.models.chat'),
        );

        $stream->each(function (StreamEvent $event) use (&$raw, &$cancelled, $onDelta, $session): bool {
            if ($this->generationWasStopped($session)) {
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

        if ($cancelled || $this->generationWasStopped($session)) {
            $this->recorder->complete($run, payload: ['grounded' => false, 'stopped' => true], retrievedChunkIds: $chunkIds);

            return $this->stoppedAssistantMessage($session);
        }

        $streamed = null;
        $stream->then(function (StreamedAgentResponse $response) use (&$streamed): void {
            $streamed = $response;
        });

        $text = $streamed instanceof StreamedAgentResponse ? $streamed->text : $raw;
        [$body, $citedRaw] = ChatCitationTrailer::split($text);
        $body = ChatAnswerCopy::normalize($body);
        $refusalPrefix = 'I don’t have a documented answer';

        if (! str_starts_with($body, $refusalPrefix)) {
            $body = SupportingPassages::includeAskedFacets($body, $question, $matches);
        }

        $cited = CitedChunkIds::onlyAllowed($citedRaw, $chunkIds);
        $cited = CitedChunkIds::usedInBody($body, $matches, $cited);
        $grounded = $cited !== [] && $body !== '';

        if (! $grounded) {
            $body = $refusal;
            $cited = [];
        }

        $this->recorder->complete($run, $streamed, ['grounded' => $grounded], $chunkIds);

        return $conversation->messages()->create([
            'role' => 'assistant',
            'body' => $body,
            'cited_chunk_ids' => $cited,
        ]);
    }

    /**
     * @phpstan-impure
     */
    private function generationWasStopped(DemoSession $session): bool
    {
        return $this->stopWasRequested($session) || connection_aborted() === 1;
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
}
