<?php

namespace App\Support;

class ChatCitationTrailer
{
    /**
     * @return array{0: string, 1: list<int>}
     */
    public static function split(string $text): array
    {
        if (preg_match('/(?:\A|\R)CITES:\s*([^\r\n]+)\s*\z/', $text, $matches) !== 1) {
            return [trim($text), []];
        }

        $body = trim((string) preg_replace('/(?:\A|\R)CITES:\s*[^\r\n]+\s*\z/', '', $text));
        $raw = strtolower(trim($matches[1]));

        if ($raw === '' || $raw === 'none') {
            return [$body, []];
        }

        $ids = [];

        foreach (preg_split('/\s*,\s*/', $matches[1]) ?: [] as $part) {
            if (is_numeric($part)) {
                $ids[] = (int) $part;
            }
        }

        return [$body, array_values(array_unique($ids))];
    }

    public static function visible(string $text): string
    {
        $cut = preg_split('/(?:\A|\R)CITES:\s*/', $text, 2);

        return trim((string) ($cut[0] ?? $text));
    }
}
