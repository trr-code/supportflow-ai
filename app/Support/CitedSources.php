<?php

namespace App\Support;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

class CitedSources
{
    /**
     * Group cited chunks under their article, preserving supporting headings.
     *
     * @param  iterable<int, KnowledgeChunk>  $chunks
     * @return list<array{article_id: int|string, title: string, headings: list<string>, chunks: list<KnowledgeChunk>}>
     */
    public static function groupByArticle(iterable $chunks): array
    {
        $groups = [];

        foreach ($chunks as $chunk) {
            $articleId = $chunk->knowledge_article_id ?: 'unknown-'.$chunk->id;
            $title = $chunk->article?->title ?: 'Knowledge article';

            $groups[$articleId] ??= [
                'article_id' => $articleId,
                'title' => $title,
                'headings' => [],
                'chunks' => [],
            ];

            $groups[$articleId]['chunks'][] = $chunk;

            $heading = trim((string) $chunk->heading);

            if ($heading !== '' && ! in_array($heading, $groups[$articleId]['headings'], true)) {
                $groups[$articleId]['headings'][] = $heading;
            }
        }

        return array_values($groups);
    }

    /**
     * @param  Collection<int, KnowledgeChunk>|iterable<int, KnowledgeChunk>  $chunks
     * @param  list<int>  $citedIds
     * @return Collection<int, KnowledgeChunk>
     */
    public static function inCitationOrder(iterable $chunks, array $citedIds): Collection
    {
        $byId = Collection::make($chunks)->keyBy('id');

        return Collection::make($citedIds)
            ->map(fn (int $id): mixed => $byId->get($id))
            ->filter(fn (mixed $chunk): bool => $chunk instanceof KnowledgeChunk)
            ->values();
    }
}
