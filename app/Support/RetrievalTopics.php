<?php

namespace App\Support;

use App\Models\KnowledgeChunk;

class RetrievalTopics
{
    /**
     * @var list<array{key: string, pattern: string, embed: string, lexical: string}>
     */
    public const CATALOG = [
        [
            'key' => 'return',
            'pattern' => '/\breturns?\b/i',
            'embed' => 'Harbor Outfitters unused return window original box prepaid UPS label',
            'lexical' => 'return window original box prepaid',
        ],
        [
            'key' => 'shipping',
            'pattern' => '/\bship(?:ping|ment)?s?\b/i',
            'embed' => 'Harbor Outfitters shipping times ground expedited warehouse',
            'lexical' => 'shipping times ground expedited',
        ],
        [
            'key' => 'warranty',
            'pattern' => '/\bwarrant(?:y|ies)\b/i',
            'embed' => 'Harbor Outfitters manufacturing warranty seam hardware failure',
            'lexical' => 'warranty manufacturing seam hardware',
        ],
        [
            'key' => 'exchange',
            'pattern' => '/\bexchanges?\b/i',
            'embed' => 'Harbor Outfitters size exchanges unused packs shells footwear',
            'lexical' => 'exchanges size unused',
        ],
    ];

    /**
     * @return list<array{key: string, embed: string, lexical: string}>
     */
    public static function matching(string $query): array
    {
        $matched = [];

        foreach (self::CATALOG as $topic) {
            if (preg_match($topic['pattern'], $query) !== 1) {
                continue;
            }

            $matched[] = [
                'key' => $topic['key'],
                'embed' => $topic['embed'],
                'lexical' => $topic['lexical'],
            ];
        }

        return $matched;
    }

    public static function chunkCovers(KnowledgeChunk $chunk, string $key): bool
    {
        $label = mb_strtolower(trim($chunk->article->title.' '.$chunk->article->slug));

        return match ($key) {
            'return' => str_contains($label, 'return'),
            'shipping' => str_contains($label, 'ship'),
            'warranty' => str_contains($label, 'warrant'),
            'exchange' => str_contains($label, 'exchange'),
            default => false,
        };
    }
}
