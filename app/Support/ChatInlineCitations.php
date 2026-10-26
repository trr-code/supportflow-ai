<?php

namespace App\Support;

use App\Models\KnowledgeChunk;
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
    protected static function mark(string $body, array $chunks): array
    {
        $marks = [];
        $numberByChunk = [];

        if (preg_match_all('/[^.!?\n]+[.!?]+|[^.!?\n]+$/u', $body, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return [$body, []];
        }

        $inserts = [];

        foreach ($matches[0] as [$sentence, $offset]) {
            if (trim($sentence) === '') {
                continue;
            }

            $pairs = [];

            foreach ($chunks as $chunk) {
                if (! CitedChunkIds::usesChunk($sentence, $chunk)) {
                    continue;
                }

                $numberByChunk[$chunk->id] ??= count($numberByChunk) + 1;
                $number = $numberByChunk[$chunk->id];
                $marks[$number] ??= self::markMeta($chunk, $number);
                $pairs[] = $number.':'.$chunk->id;
            }

            if ($pairs === []) {
                continue;
            }

            $inserts[$offset + strlen($sentence)] = '⟦c:'.implode(',', array_unique($pairs)).'⟧';
        }

        krsort($inserts);

        foreach ($inserts as $position => $marker) {
            $body = substr($body, 0, $position).$marker.substr($body, $position);
        }

        return [$body, $marks];
    }

    /**
     * @param  array<int, array{number: int, chunk_id: int, article_id: string, title: string, heading: string|null, excerpt: string}>  $marks
     */
    protected static function replaceMarks(string $html, array $marks, ?int $messageId): string
    {
        $replaced = preg_replace_callback(
            '/⟦c:([0-9]+(?::[0-9]+)?(?:,[0-9]+(?::[0-9]+)?)*)⟧/',
            function (array $match) use ($marks, $messageId): string {
                $buttons = '';

                foreach (explode(',', $match[1]) as $pair) {
                    [$number] = array_pad(explode(':', $pair, 2), 2, '');
                    $mark = $marks[(int) $number] ?? null;

                    if ($mark === null) {
                        continue;
                    }

                    $buttons .= self::button((int) $number, $mark, $messageId);
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
            'excerpt' => Str::limit($excerpt, 280, ''),
        ];
    }
}
