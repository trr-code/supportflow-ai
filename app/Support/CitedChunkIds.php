<?php

namespace App\Support;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

class CitedChunkIds
{
    /**
     * @param  list<int>  $allowed
     * @return list<int>
     */
    public static function onlyAllowed(mixed $ids, array $allowed): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $cited = [];

        foreach ($ids as $id) {
            $int = (int) $id;

            if (in_array($int, $allowed, true) && ! in_array($int, $cited, true)) {
                $cited[] = $int;
            }
        }

        return $cited;
    }

    /**
     * Add retrieved chunks whose wording actually appears in the draft.
     *
     * @param  Collection<int, array{chunk: KnowledgeChunk, similarity: float}>  $matches
     * @param  list<int>  $cited
     * @return list<int>
     */
    public static function usedInBody(string $body, Collection $matches, array $cited): array
    {
        foreach ($matches as $row) {
            $id = (int) $row['chunk']->id;

            if (in_array($id, $cited, true)) {
                continue;
            }

            if (self::usesChunk($body, $row['chunk'])) {
                $cited[] = $id;
            }
        }

        return $cited;
    }

    public static function usesChunk(string $body, KnowledgeChunk $chunk): bool
    {
        $haystack = self::normalize($body);
        $source = self::normalize(trim(($chunk->heading ?? '').' '.$chunk->body));
        $words = array_values(array_filter(explode(' ', $source), fn (string $word): bool => $word !== ''));
        $window = 5;

        if ($source === '') {
            return false;
        }

        if (count($words) < $window) {
            return str_contains($haystack, $source);
        }

        for ($i = 0; $i <= count($words) - $window; $i++) {
            if (str_contains($haystack, implode(' ', array_slice($words, $i, $window)))) {
                return true;
            }
        }

        return false;
    }

    protected static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
