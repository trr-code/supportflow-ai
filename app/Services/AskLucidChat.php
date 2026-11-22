<?php

namespace App\Services;

use App\Ai\Agents\AskLucidAgent;
use App\Enums\AiRunFeature;
use App\Support\ChatAnswerHtml;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

class AskLucidChat
{
    public function __construct(private readonly AiUsageRecorder $recorder) {}

    public function key(string $sessionId): string
    {
        return 'ask-lucid:'.hash('sha256', $sessionId);
    }

    public function lock(string $key): Lock
    {
        return Cache::lock($key.':lock', 90);
    }

    public function isAnswering(string $key): bool
    {
        $lock = $this->lock($key);
        if (! $lock->get()) {
            return true;
        }
        $lock->release();

        return false;
    }

    /** @return list<array{role: string, content: string}> */
    public function history(string $key): array
    {
        return Cache::get($key.':history', []);
    }

    public function interrupt(string $key, bool $clear = false, bool $retain = false, ?int $characters = null, ?string $generation = null): void
    {
        $interrupted = Cache::lock($key.':state', 5)->block(2, function () use ($key, $clear, $retain, $characters, $generation): bool {
            if ($generation !== null && Cache::get($key.':generation') !== $generation) {
                return false;
            }
            $pending = Cache::get($key.':pending');
            if (is_array($pending) && ! $pending['saved']) {
                $pending['text'] = Cache::get($key.':pending:'.$pending['generation'], $pending['text']);
            }
            if (! $clear && $retain && is_array($pending)
                && $pending['generation'] === Cache::get($key.':generation')
                && ($generation === null || $generation === $pending['generation'])) {
                $history = $this->history($key);
                if ($pending['saved']) {
                    $history = array_slice($history, 0, -2);
                }
                $prefix = $characters === null ? '' : ChatAnswerHtml::revealedText($pending['text'], $characters);
                $history[] = ['role' => 'user', 'content' => $pending['question']];
                $history[] = ['role' => 'assistant', 'content' => $prefix === '' ? 'Stopped.' : $prefix."\n\nStopped."];
                Cache::put($key.':history', array_slice($history, -24), now()->addMinutes(45));
            }
            $this->begin($key);
            if (is_array($pending)) {
                Cache::forget($key.':pending:'.$pending['generation']);
            }
            Cache::forget($key.':pending');
            if ($clear) {
                Cache::forget($key.':history');
            }

            return true;
        });
        if ($interrupted) {
            $this->lock($key)->forceRelease();
        }
    }

    public function begin(string $key, ?string $question = null): string
    {
        $generation = (string) Str::uuid();
        Cache::put($key.':generation', $generation, now()->addMinutes(45));
        if ($question !== null) {
            Cache::put($key.':pending', ['generation' => $generation, 'question' => $question, 'text' => '', 'saved' => false], now()->addMinutes(45));
        }

        return $generation;
    }

    /** @param Closure(string, array<string, mixed>): void $emit */
    public function answer(string $key, string $question, string $page, string $layout, Closure $emit, ?string $generation = null): void
    {
        $generation ??= $this->begin($key, $question);
        if (Cache::get($key.':generation') !== $generation) {
            $emit('stopped', []);

            return;
        }
        $history = $this->history($key);
        $model = (string) config('supportflow.models.chat');
        $run = $this->recorder->start(AiRunFeature::AskLucid, null, $model);
        $text = '';
        $completed = null;
        $nextStopCheck = 0.0;
        $nextSnapshot = 0.0;

        try {
            $stream = AskLucidAgent::make(history: $history)->stream(
                "Page hint: {$page}. Layout hint: {$layout}. These are orientation only.\nVisitor question: ".$question,
                provider: Lab::OpenAI, model: $model,
            );
            $stream->then(function (StreamedAgentResponse $response) use (&$completed): void {
                $completed = $response;
            });
            foreach ($stream as $event) {
                $checkStop = microtime(true) >= $nextStopCheck;
                if ($checkStop) {
                    $nextStopCheck = microtime(true) + 0.15;
                }
                if (($checkStop && Cache::get($key.':generation') !== $generation) || connection_aborted()) {
                    $this->recorder->complete($run, payload: ['stopped' => true]);
                    $emit('stopped', []);

                    return;
                }
                if ($event instanceof TextDelta) {
                    $text .= $event->delta;
                    if (microtime(true) >= $nextSnapshot) {
                        $nextSnapshot = microtime(true) + 0.5;
                        Cache::put($key.':pending:'.$generation, $text, now()->addSeconds(90));
                    }
                    $emit('delta', ['html' => ChatAnswerHtml::render($text)]);
                }
            }
            if (Cache::get($key.':generation') !== $generation) {
                $this->recorder->complete($run, $completed, ['stopped' => true]);
                $emit('stopped', []);

                return;
            }
            $text = trim($text);
            if ($text === '') {
                throw new \RuntimeException('Ask Lucid returned an empty answer.');
            }
            $history[] = ['role' => 'user', 'content' => $question];
            $history[] = ['role' => 'assistant', 'content' => $text];
            $saved = Cache::lock($key.':state', 5)->block(2, function () use ($key, $generation, $history, $question, $text): bool {
                if (Cache::get($key.':generation') !== $generation) {
                    return false;
                }
                Cache::put($key.':history', array_slice($history, -24), now()->addMinutes(45));
                Cache::put($key.':pending', ['generation' => $generation, 'question' => $question, 'text' => $text, 'saved' => true], now()->addMinutes(45));
                Cache::forget($key.':pending:'.$generation);

                return true;
            });
            $this->recorder->complete($run, $completed);
            $emit($saved ? 'done' : 'stopped', $saved ? ['html' => ChatAnswerHtml::render($text)] : []);
        } catch (Throwable $exception) {
            Cache::lock($key.':state', 5)->block(2, function () use ($key, $generation): void {
                Cache::forget($key.':pending:'.$generation);
                if (Cache::get($key.':generation') === $generation) {
                    Cache::forget($key.':pending');
                }
            });
            $this->recorder->fail($run, 'Ask Lucid could not finish the answer.');
            throw $exception;
        }
    }
}
