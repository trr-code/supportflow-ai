<?php

namespace App\Http\Controllers;

use App\Services\AskLucidChat;
use App\Support\ChatAnswerHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AskLucidController extends Controller
{
    public function index(Request $request, AskLucidChat $chat): JsonResponse
    {
        $key = $this->conversationKey($request, $chat);
        $messages = array_map(fn (array $turn): array => [
            'role' => $turn['role'],
            'content' => $turn['content'],
            'html' => ChatAnswerHtml::render(preg_replace('/(?:\n\n)?Stopped\.$/u', '', $turn['content']) ?? $turn['content']),
            'stopped' => $turn['role'] === 'assistant' && str_ends_with($turn['content'], 'Stopped.'),
        ], $chat->history($key));

        return response()->json(['messages' => $messages, 'answering' => $chat->isAnswering($key)])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, AskLucidChat $chat): JsonResponse|StreamedResponse
    {
        $input = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'page' => ['required', Rule::in(['home', 'demo.harbor', 'demo.documents', 'demo.invoices', 'dashboard', 'agent.tickets.index', 'agent.tickets.show', 'knowledge.index', 'knowledge.show', 'tickets.status', 'demo.environment'])],
            'layout' => ['required', Rule::in(['columns', 'stacked'])],
            'stage' => ['nullable', Rule::in(['unspecified', 'documents-upload', 'documents-processing', 'documents-failed', 'documents-ready', 'documents-answers', 'documents-answers-without-sources', 'invoices-upload', 'invoices-processing', 'invoices-failed', 'invoices-needs_review', 'invoices-confirmed', 'support-chat', 'support-ticket', 'support-human', 'support-status'])],
            'instructions' => ['nullable', Rule::in(['visible', 'hidden', 'unspecified'])],
        ]);
        $key = $this->conversationKey($request, $chat);
        $lock = $chat->lock($key);
        if (! $lock->get()) {
            return response()->json(['message' => 'Please wait for the current answer, or select Stop answer.'], 409);
        }

        $generation = $chat->begin($key, $input['question']);

        return response()->stream(function () use ($chat, $key, $input, $lock, $generation): void {
            $flush = ! app()->runningUnitTests();
            $emit = function (string $event, array $data) use ($flush): void {
                echo 'event: '.$event."\n".'data: '.json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE)."\n\n";
                if ($flush) {
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            };
            try {
                $emit('start', ['generation' => $generation]);
                $page = $input['page'].'; task stage: '.($input['stage'] ?? 'unspecified').'; instructions: '.($input['instructions'] ?? 'unspecified');
                $chat->answer($key, $input['question'], $page, $input['layout'], $emit, $generation);
            } catch (Throwable $exception) {
                report($exception);
                $emit('error', ['message' => 'Ask Lucid could not finish. Your question is still available to try again.']);
            } finally {
                $lock->release();
            }
        }, 200, ['Content-Type' => 'text/event-stream; charset=UTF-8', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
    }

    public function update(Request $request, AskLucidChat $chat): JsonResponse
    {
        $input = $request->validate(['characters' => ['nullable', 'integer', 'min:0', 'max:20000'], 'generation' => ['nullable', 'uuid']]);
        $chat->interrupt($this->conversationKey($request, $chat), retain: true, characters: $input['characters'] ?? null, generation: $input['generation'] ?? null);

        return response()->json(['stopped' => true]);
    }

    public function destroy(Request $request, AskLucidChat $chat): JsonResponse
    {
        $chat->interrupt($this->conversationKey($request, $chat), clear: true);

        return response()->json(['messages' => []]);
    }

    private function conversationKey(Request $request, AskLucidChat $chat): string
    {
        $id = $request->session()->get('ask_lucid_id');
        if (! is_string($id)) {
            $id = (string) Str::uuid();
            $request->session()->put('ask_lucid_id', $id);
        }

        return $chat->key($id);
    }
}
