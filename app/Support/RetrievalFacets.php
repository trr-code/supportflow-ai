<?php

namespace App\Support;

use App\Models\KnowledgeChunk;

class RetrievalFacets
{
    /**
     * Intra-article facets detected from the visitor query only.
     *
     * @var list<array{key: string, pattern: string, embed: string, lexical: string}>
     */
    public const CATALOG = [
        [
            'key' => 'deadline',
            'pattern' => '/\b(?:return window|deadline|how long|\d+\s*days?|unused.{0,80}tags|tags.{0,40}attached)\b/i',
            'embed' => 'Harbor Outfitters unused return window 30 days tags attached',
            'lexical' => 'unused returns 30 days tags',
        ],
        [
            'key' => 'box',
            'pattern' => '/\b(?:original(?: shipping)? box|shipping box|packaging|without (?:the )?(?:original )?box|no longer have the original box|sturdy carton|carton)\b/i',
            'embed' => 'Harbor Outfitters original shipping box not required sturdy carton',
            'lexical' => 'original shipping box sturdy carton',
        ],
        [
            'key' => 'prepaid',
            'pattern' => '/\b(?:prepaid|labels?|next steps?|order portal)\b/i',
            'embed' => 'Harbor Outfitters prepaid UPS label email order portal',
            'lexical' => 'prepaid UPS label order portal',
        ],
        [
            'key' => 'gift_capture',
            'pattern' => '/\bgift cards?\b/i',
            'embed' => 'Harbor gift cards combined with a credit card gift card is captured first',
            'lexical' => 'gift card captured first combined credit card',
        ],
        [
            'key' => 'duplicate_charge',
            'pattern' => '/\b(?:charged.{0,40}(?:twice|full)|both charged|duplicate charge)\b/i',
            'embed' => 'If a card and gift card were both charged in full we reverse the card charge within 3 business days after review',
            'lexical' => 'gift card charged in full reverse card 3 business days',
        ],
        [
            'key' => 'split_tender',
            'pattern' => '/\b(?:gift card.{0,80}visa|visa.{0,80}gift card|split tender)\b/i',
            'embed' => 'Split tender orders show two authorizations gift card covers the balance',
            'lexical' => 'split tender two authorizations gift card',
        ],
        [
            'key' => 'store_pickup',
            'pattern' => '/\b(?:store pickup|pickup option|same-day pickup|flagship)\b/i',
            'embed' => 'Seattle Flagship and Portland Pearl can hold replacement parts for same-day pickup',
            'lexical' => 'store pickup Seattle Flagship Portland Pearl replacement parts',
        ],
        [
            'key' => 'privacy_demo',
            'pattern' => '/\b(?:real payment|real customers?|real orders?|are (?:customers|orders) real|fictional demo|portfolio demo|demo environment|invented information|privacy (?:policy|notice|demo))\b/i',
            'embed' => 'live portfolio demo no real customers orders or payments do not enter real personal order or payment information',
            'lexical' => 'portfolio demo no real customers orders payments',
        ],
        [
            'key' => 'order_lookup',
            'pattern' => '/\b(?:order\s*HB-\d+|where(?:\'s| is) (?:my )?(?:order|package|shipment)|look up (?:my |the )?order|track my (?:order|package))\b/i',
            'embed' => 'This demo cannot access real order records or live shipment locations',
            'lexical' => 'demo cannot access real order records',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function matching(string $query): array
    {
        return array_column(self::queries($query), 'key');
    }

    /**
     * @return list<array{key: string, embed: string, lexical: string}>
     */
    public static function queries(string $query): array
    {
        $matched = [];

        foreach (self::CATALOG as $facet) {
            if (preg_match($facet['pattern'], $query) !== 1) {
                continue;
            }

            $matched[] = [
                'key' => $facet['key'],
                'embed' => $facet['embed'],
                'lexical' => $facet['lexical'],
            ];
        }

        return $matched;
    }

    public static function chunkCovers(KnowledgeChunk $chunk, string $key): bool
    {
        $heading = mb_strtolower(trim((string) $chunk->heading));
        $haystack = mb_strtolower(trim($heading.' '.$chunk->body));

        return match ($key) {
            'deadline' => $heading === 'window'
                || str_contains($haystack, 'within 30 days of delivery')
                || str_contains($haystack, 'unused returns within 30 days'),
            'box' => $heading === 'box not required'
                || str_contains($haystack, 'original shipping box')
                || str_contains($haystack, 'sturdy carton'),
            'prepaid' => $heading === 'prepaid labels'
                || str_contains($haystack, 'prepaid')
                || str_contains($haystack, 'ups label'),
            'gift_capture' => str_contains($haystack, 'captured first'),
            'duplicate_charge' => $heading === 'duplicate charges'
                || str_contains($haystack, 'reverse the card charge within 3')
                || str_contains($haystack, 'within 3 business days after review'),
            'split_tender' => str_contains($haystack, 'two authorizations')
                || str_contains($haystack, 'split tender'),
            'store_pickup' => str_contains($haystack, 'same-day pickup')
                || str_contains($haystack, 'seattle flagship')
                || str_contains($haystack, 'portland pearl'),
            'privacy_demo' => str_contains($haystack, 'no real customers')
                || str_contains($haystack, 'enter invented information')
                || str_contains($haystack, 'do not enter real personal')
                || str_contains($haystack, 'does not store real payment'),
            'order_lookup' => str_contains($haystack, 'cannot access real order records')
                || str_contains($haystack, 'live shipment locations'),
            default => false,
        };
    }
}
