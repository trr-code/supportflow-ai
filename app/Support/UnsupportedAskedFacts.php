<?php

namespace App\Support;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

class UnsupportedAskedFacts
{
    /**
     * Narrow refusal for questions the retrieved passages cannot fully answer.
     *
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $matches
     */
    public static function refusalReason(string $query, Collection $matches): ?string
    {
        $passage = $matches->map(function (array $row): string {
            $chunk = $row['chunk'];
            $article = $chunk->article;

            return mb_strtolower(trim(
                $article->title.' '.$article->slug.' '.($chunk->heading ?? '').' '.$chunk->body
            ));
        })->implode("\n");

        if (self::asksThreadColors($query) && ! self::coversThreadColors($passage)) {
            return 'unsupported';
        }

        return null;
    }

    public static function asksThreadColors(string $query): bool
    {
        return preg_match('/\b(?:thread colou?rs?|embroidery colou?rs?)\b/i', $query) === 1;
    }

    public static function coversThreadColors(string $passage): bool
    {
        return preg_match('/\b(?:thread colou?rs?|embroidery colou?rs?|available colou?rs?|color options)\b/i', $passage) === 1;
    }
}
