<?php

namespace App\Support;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

class SupportingPassages
{
    /**
     * Include retrieved passages that cover asked facets when the draft omitted them.
     *
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $matches
     */
    public static function includeAskedFacets(string $body, string $query, Collection $matches): string
    {
        foreach (['split_tender', 'store_pickup', 'privacy_demo', 'order_lookup'] as $facet) {
            $body = self::includeUnusedCovering($body, $query, $matches, $facet);
        }

        return $body;
    }

    /**
     * Include a retrieved split-tender passage when the ticket asks about dual-tender charges
     * and the draft has not already used that passage.
     *
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $matches
     */
    public static function includeSplitTender(string $body, string $query, Collection $matches): string
    {
        return self::includeUnusedCovering($body, $query, $matches, 'split_tender');
    }

    /**
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $matches
     */
    protected static function includeUnusedCovering(string $body, string $query, Collection $matches, string $facet): string
    {
        if (! in_array($facet, RetrievalFacets::matching($query), true)) {
            return $body;
        }

        foreach ($matches as $row) {
            $chunk = $row['chunk'];

            if (! RetrievalFacets::chunkCovers($chunk, $facet)) {
                continue;
            }

            if (CitedChunkIds::usesChunk($body, $chunk)) {
                continue;
            }

            $body = trim($body)."\n\n".trim($chunk->body);
        }

        return $body;
    }
}
