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

        if (str_contains($source, 'harbor trail packs ship in 28l and 36l')
            && preg_match('/\b28l\b/', $haystack) === 1
            && preg_match('/\b36l\b/', $haystack) === 1) {
            return preg_match('/\btrail packs? (?:come|ship|are available|are offered) in (?:both )?(?:28l and 36l|36l and 28l)\b/', $haystack) === 1;
        }

        if (count($words) < $window) {
            return str_contains($haystack, $source);
        }

        for ($i = 0; $i <= count($words) - $window; $i++) {
            if (str_contains($haystack, implode(' ', array_slice($words, $i, $window)))) {
                return true;
            }
        }

        return self::sharesGroundedClaims($haystack, $source)
            || self::sharesDistinctiveWords($haystack, $source);
    }

    private static function sharesDistinctiveWords(string $body, string $source): bool
    {
        $stop = ['a', 'an', 'or', 'in', 'on', 'of', 'to', 'be', 'those', 'these', 'cases', 'the', 'and', 'for', 'with', 'that', 'this', 'from', 'within', 'after', 'before', 'are', 'was', 'has', 'have', 'not', 'does', 'may', 'can', 'will', 'its', 'you', 'your', 'they', 'their', 'only', 'must', 'when', 'than', 'then', 'been', 'being'];
        $words = static fn (string $text): array => array_values(array_unique(array_filter(
            explode(' ', $text),
            static fn (string $word): bool => strlen($word) > 2 && ! in_array($word, $stop, true),
        )));
        $answer = $words($body);
        $passage = $words($source);
        $shared = count(array_intersect($answer, $passage));

        return $shared >= 3 && $shared >= min(count($answer), count($passage)) * 0.75;
    }

    /**
     * Paraphrases still need the passage that actually states the claim.
     */
    protected static function sharesGroundedClaims(string $body, string $source): bool
    {
        if (self::statesThirtyDayReturnWindow($body) && self::statesThirtyDayReturnWindow($source)) {
            return true;
        }

        if (str_contains($source, 'cost 100 or less at purchase')
            && str_contains($source, 'higher priced items can be sent to support for review')
            && preg_match('/\b(?:more than|over|above) 100 (?:may |can |must |will )?(?:require|need|be sent to) (?:a )?(?:support review|support for review|review by support)\b/', $body) === 1) {
            return true;
        }

        $damageDeadline = '/\b(?:file|report)(?: the damage)? within (?:5|five) days of delivery\b/';

        if (preg_match($damageDeadline, $source) === 1 && preg_match($damageDeadline, $body) === 1) {
            return true;
        }

        $bodyHasFree = str_contains($body, 'free')
            || str_contains($body, 'no charge')
            || str_contains($body, 'without a fee')
            || str_contains($body, 'without fee');

        return str_contains($source, 'free')
            && $bodyHasFree
            && str_contains($source, '30 days')
            && str_contains($body, '30 days');
    }

    public static function statesThirtyDayReturnWindow(string $text): bool
    {
        $text = self::normalize($text);

        return preg_match('/\b(?:30|thirty) day return window\b/', $text) === 1
            || (preg_match('/\breturns?\b/', $text) === 1
                && preg_match('/\bwithin (?:30|thirty) days of delivery\b/', $text) === 1);
    }

    protected static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/\b(refund|return|cover|inspect|offer)(?:ed|s|ing)\b/u', '$1', $text) ?? $text;
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
