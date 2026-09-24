<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\KnowledgeChunk;
use App\Services\ChatService;
use App\Services\DemoSessionService;
use App\Support\ChatAnswerHtml;
use App\Support\CitedSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StreamChatController extends Controller
{
    public function __invoke(
        Request $request,
        DemoSessionService $sessions,
        ChatService $chat,
    ): JsonResponse|StreamedResponse {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        $session = $sessions->heartbeat($request);
        $sessions->queueCookie($session);

        $lock = $chat->streamLock($session);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'Please wait for the current answer to finish.',
                'errors' => [
                    'question' => ['Please wait for the current answer to finish.'],
                ],
            ], Response::HTTP_CONFLICT);
        }

        try {
            return response()->stream(function () use ($lock, $chat, $session, $validated): void {
                $shouldFlush = ! app()->runningUnitTests();

                $emit = function (string $event, array $data) use ($shouldFlush): void {
                    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    if ($payload === false) {
                        return;
                    }

                    echo "event: {$event}\n";
                    echo "data: {$payload}\n\n";

                    if (! $shouldFlush) {
                        return;
                    }

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                };

                if ($shouldFlush) {
                    while (ob_get_level() > 0) {
                        ob_end_flush();
                    }
                }

                try {
                    $message = $chat->ask(
                        $session,
                        $validated['question'],
                        function (string $visible) use ($emit): void {
                            $html = $visible === '' ? '' : ChatAnswerHtml::render($visible);
                            $emit('delta', ['html' => $html]);
                        },
                    );

                    $event = $message->body === 'Stopped.' ? 'stopped' : 'done';
                    $emit($event, self::terminalPayload($event, $message));
                } catch (Throwable $exception) {
                    report($exception);
                    $emit('error', [
                        'message' => 'The assistant could not finish that answer. Try again.',
                    ]);
                } finally {
                    $chat->clearStopRequest($session);
                    $lock->release();
                }
            }, Response::HTTP_OK, [
                'Content-Type' => 'text/event-stream; charset=UTF-8',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        } catch (Throwable $exception) {
            $lock->release();

            throw $exception;
        }
    }

    /**
     * @return array{id: int, html?: string, sources?: list<array{article_id: int|string, title: string, headings: list<string>, includes_intro: bool}>}
     */
    private static function terminalPayload(string $event, ChatMessage $message): array
    {
        $payload = ['id' => (int) $message->id];

        if ($event !== 'done') {
            return $payload;
        }

        $citedIds = array_values(array_map(intval(...), $message->cited_chunk_ids ?? []));
        $payload['sources'] = $citedIds === []
            ? []
            : CitedSources::streamLabels(
                KnowledgeChunk::query()->with('article')->whereIn('id', $citedIds)->get(),
                $citedIds,
            );

        if ($message->body !== '') {
            $payload['html'] = ChatAnswerHtml::render($message->body);
        }

        return $payload;
    }
}
