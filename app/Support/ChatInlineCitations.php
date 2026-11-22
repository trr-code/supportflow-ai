<?php

namespace App\Support;

use App\Models\KnowledgeChunk;
use Closure;
use Illuminate\Support\Str;

class ChatInlineCitations
{
    /**
     * Render an answer with a numbered circle beside each sentence a cited passage supports.
     *
     * @param  iterable<int, KnowledgeChunk>  $chunks
     */
    public static function render(string $body, iterable $chunks, ?int $messageId = null): string
    {
        $ordered = self::chunks($chunks);

        if ($ordered === []) {
            return ChatAnswerHtml::render($body);
        }

        [$marked, $marks] = self::mark($body, $ordered);
        $html = ChatAnswerHtml::render($marked);

        return self::replaceMarks($html, $marks, $messageId);
    }

    /**
     * @param  iterable<int, KnowledgeChunk>  $chunks
     * @param  list<array{chunk_id: int, title: string, heading: string|null, excerpt: string, passage: string}>  $sources
     */
    public static function renderWorkspace(string $body, iterable $chunks, array $sources): string
    {
        $sourceById = array_column($sources, null, 'chunk_id');
        [$marked, $marks] = self::mark($body, self::chunks($chunks), workspace: true);

        return self::replaceMarks(ChatAnswerHtml::render($marked), $marks, null, function (int $number, array $mark) use ($sourceById): string {
            $source = $sourceById[$mark['chunk_id']];
            $encoded = e(json_encode($source, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));

            return '<button type="button" class="harbor-cite document-source-trigger"'
                .' data-source="'.$encoded.'"'
                .' data-cite-title="'.e($source['title']).'" data-cite-excerpt="'.e($source['excerpt']).'"'
                .' x-on:mouseenter="pointerOverCite($event)" x-on:mouseleave="pointerLeftCite()"'
                .' x-on:focus="focusCite($event)" x-on:blur="focusLeftCite()" x-on:keydown.escape="closeCite()"'
                .' x-on:click="inspectSource(JSON.parse($el.dataset.source), $el)"'
                .' aria-label="'.e('Read source '.$number.': '.$source['title']).'">'.$number.'</button>';
        });
    }

    /**
     * Numbered passages for one answer, in the same order as its citation circles.
     *
     * @param  iterable<int, KnowledgeChunk>  $chunks
     * @return list<array{article_id: string, title: string, headings: list<string>, passages: list<array{number: int, chunk_id: int, excerpt: string}>}>
     */
    public static function groups(string $body, iterable $chunks): array
    {
        $groups = [];

        foreach (self::placements($body, $chunks) as $placement) {
            $articleId = $placement['article_id'];

            $groups[$articleId] ??= [
                'article_id' => $articleId,
                'title' => $placement['title'],
                'headings' => [],
                'passages' => [],
            ];

            if ($placement['heading'] !== null && ! in_array($placement['heading'], $groups[$articleId]['headings'], true)) {
                $groups[$articleId]['headings'][] = $placement['heading'];
            }

            $groups[$articleId]['passages'][] = [
                'number' => $placement['number'],
                'chunk_id' => $placement['chunk_id'],
                'excerpt' => $placement['excerpt'],
            ];
        }

        return array_values($groups);
    }

    /**
     * @param  iterable<int, KnowledgeChunk>  $chunks
     * @return list<array{number: int, chunk_id: int, article_id: string, title: string, heading: string|null, excerpt: string}>
     */
    public static function placements(string $body, iterable $chunks): array
    {
        $ordered = self::chunks($chunks);

        if ($ordered === []) {
            return [];
        }

        [, $marks] = self::mark($body, $ordered);
        ksort($marks);

        return array_values($marks);
    }

    /**
     * @param  iterable<int, KnowledgeChunk>  $chunks
     * @return list<KnowledgeChunk>
     */
    protected static function chunks(iterable $chunks): array
    {
        $ordered = [];

        foreach ($chunks as $chunk) {
            if ($chunk instanceof KnowledgeChunk) {
                $ordered[] = $chunk;
            }
        }

        return $ordered;
    }

    /**
     * @param  list<KnowledgeChunk>  $chunks
     * @return array{0: string, 1: array<int, array{number: int, chunk_id: int, article_id: string, title: string, heading: string|null, excerpt: string}>}
     */
    protected static function mark(string $body, array $chunks, bool $workspace = false): array
    {
        $marks = [];
        $numberByChunk = [];

        $sentencePattern = '/[^\n]+?(?:[.!?]+(?=\s|$)(?!(?<=\b[ap]\.m\.)[ \t]+(?:(?-i:\p{Ll})|(?:Central|Eastern|Mountain|Pacific|Alaska|Hawaii|UTC|GMT|[ECMP][SD]T)\b))|(?=\n|$))/iu';

        if (preg_match_all($sentencePattern, $body, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return [$body, []];
        }

        $inserts = [];
        $previousChunkIds = [];

        foreach ($matches[0] as [$sentence, $offset]) {
            if (trim($sentence) === '') {
                continue;
            }

            $pairs = [];
            $currentChunkIds = [];
            $position = $offset + strlen($sentence);
            $directChunks = array_filter($chunks, fn (KnowledgeChunk $chunk): bool => CitedChunkIds::usesChunk($sentence, $chunk));
            $supportingChunks = $directChunks !== [] ? $directChunks : array_filter($chunks, fn (KnowledgeChunk $chunk): bool => $workspace && self::sharesAmount($sentence, $chunk->body));

            foreach ($workspace ? $supportingChunks : self::preferDirectReturnWindow($sentence, $supportingChunks) as $chunk) {
                $numberByChunk[$chunk->id] ??= count($numberByChunk) + 1;
                $number = $numberByChunk[$chunk->id];
                $marks[$number] ??= self::markMeta($chunk, $number);
                $pair = $number.':'.$chunk->id;
                $previousPosition = $previousChunkIds[$chunk->id] ?? null;
                if ($previousPosition !== null) {
                    unset($inserts[$previousPosition][$chunk->id]);
                }
                $currentChunkIds[$chunk->id] = $position;
                $pairs[$chunk->id] = $pair;
            }

            $previousChunkIds = $currentChunkIds;

            if ($pairs === []) {
                continue;
            }

            $inserts[$position] = $pairs;
        }

        krsort($inserts);

        foreach ($inserts as $position => $pairs) {
            if ($pairs !== []) {
                $body = substr($body, 0, $position).'⟦c:'.implode(',', $pairs).'⟧'.substr($body, $position);
            }
        }

        if ($workspace && $marks === []) {
            foreach ($chunks as $chunk) {
                if (! isset($numberByChunk[$chunk->id])) {
                    $number = count($marks) + 1;
                    $marks[$number] = self::markMeta($chunk, $number);
                    $body .= '⟦c:'.$number.':'.$chunk->id.'⟧';
                }
            }
        }

        $visibleMarks = [];
        $visibleNumbers = [];
        $body = preg_replace_callback('/⟦c:([0-9:,]+)⟧/', function (array $match) use ($marks, &$visibleMarks, &$visibleNumbers): string {
            $pairs = [];
            foreach (explode(',', $match[1]) as $pair) {
                [$oldNumber, $chunkId] = array_pad(explode(':', $pair, 2), 2, '');
                $mark = $marks[(int) $oldNumber] ?? null;
                if ($mark === null || $mark['chunk_id'] !== (int) $chunkId) {
                    continue;
                }
                $visibleNumbers[$chunkId] ??= count($visibleNumbers) + 1;
                $number = $visibleNumbers[$chunkId];
                $visibleMarks[$number] = array_replace($mark, ['number' => $number]);
                $pairs[] = $number.':'.$chunkId;
            }

            return $pairs === [] ? '' : '⟦c:'.implode(',', $pairs).'⟧';
        }, $body) ?? $body;

        return [$body, $visibleMarks];
    }

    private static function sharesAmount(string $sentence, string $passage): bool
    {
        preg_match_all('/\$\d[\d,]*(?:\.\d{2})?(?!\d)/', $sentence, $answerAmounts);
        preg_match_all('/\$\d[\d,]*(?:\.\d{2})?(?!\d)/', $passage, $sourceAmounts);

        return array_intersect($answerAmounts[0], $sourceAmounts[0]) !== [];
    }

    /**
     * @param  array<int, KnowledgeChunk>  $chunks
     * @return array<int, KnowledgeChunk>
     */
    private static function preferDirectReturnWindow(string $sentence, array $chunks): array
    {
        if ((! CitedChunkIds::statesThirtyDayReturnWindow($sentence)
                && preg_match('/\bunused\b/i', $sentence) !== 1)
            || preg_match('/\$|\b(?:chat|approv\w*|confirm\w*|final.sale|refund\w*|label\w*|reference\w*|staff|review)\b/i', $sentence)) {
            return $chunks;
        }

        foreach ($chunks as $chunk) {
            if ($chunk->article->slug === 'return-window' && $chunk->heading !== 'Start a return in chat'
                && CitedChunkIds::statesThirtyDayReturnWindow($chunk->body)) {
                return array_filter($chunks, fn (KnowledgeChunk $source): bool => $source->knowledge_article_id !== $chunk->knowledge_article_id
                    || $source->heading !== 'Start a return in chat');
            }
        }

        return $chunks;
    }

    /**
     * @param  array<int, array{number: int, chunk_id: int, article_id: string, title: string, heading: string|null, excerpt: string}>  $marks
     */
    protected static function replaceMarks(string $html, array $marks, ?int $messageId, ?Closure $renderButton = null): string
    {
        $replaced = preg_replace_callback(
            '/⟦c:([0-9]+(?::[0-9]+)?(?:,[0-9]+(?::[0-9]+)?)*)⟧/',
            function (array $match) use ($marks, $messageId, $renderButton): string {
                $buttons = '';

                foreach (explode(',', $match[1]) as $pair) {
                    [$number] = array_pad(explode(':', $pair, 2), 2, '');
                    $mark = $marks[(int) $number] ?? null;

                    if ($mark === null) {
                        continue;
                    }

                    $buttons .= $renderButton === null
                        ? self::button((int) $number, $mark, $messageId)
                        : $renderButton((int) $number, $mark);
                }

                return $buttons;
            },
            $html,
        );

        return $replaced ?? $html;
    }

    /**
     * @param  array{number: int, chunk_id: int, article_id: string, title: string, heading: string|null, excerpt: string}  $mark
     */
    protected static function button(int $number, array $mark, ?int $messageId): string
    {
        $title = e($mark['title']);
        $excerpt = e($mark['excerpt']);
        $label = e('Citation '.$number.', '.$mark['title']);
        $payload = [
            'articleId' => $mark['article_id'],
            'chunkId' => $mark['chunk_id'],
        ];

        if ($messageId !== null) {
            $payload = ['messageId' => $messageId] + $payload;
        }

        $encoded = e(json_encode($payload, JSON_THROW_ON_ERROR));

        return '<button type="button"'
            .' class="harbor-cite"'
            .' data-cite-title="'.$title.'"'
            .' data-cite-excerpt="'.$excerpt.'"'
            .' x-on:mouseenter="pointerOverCite($event)"'
            .' x-on:focus="focusCite($event)"'
            .' x-on:mouseleave="pointerLeftCite()"'
            .' x-on:blur="focusLeftCite()"'
            .' x-on:keydown.escape="closeCite()"'
            .' wire:click="$dispatch(\'harbor-inspect-citation\', '.$encoded.')"'
            .' aria-label="'.$label.'"'
            .'>'.$number.'</button>';
    }

    /**
     * @return array{number: int, chunk_id: int, article_id: string, title: string, heading: string|null, excerpt: string}
     */
    protected static function markMeta(KnowledgeChunk $chunk, int $number): array
    {
        $excerpt = preg_replace('/\s+/', ' ', trim((string) $chunk->body)) ?? '';
        $heading = trim((string) ($chunk->heading ?? ''));

        return [
            'number' => $number,
            'chunk_id' => (int) $chunk->id,
            'article_id' => (string) ($chunk->knowledge_article_id ?: 'unknown-'.$chunk->id),
            'title' => $chunk->article?->title ?: 'Knowledge article',
            'heading' => $heading !== '' ? $heading : null,
            'excerpt' => Str::limit($excerpt, 280, '…', preserveWords: true),
        ];
    }
}
