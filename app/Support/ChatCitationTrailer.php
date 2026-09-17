<?php

namespace App\Support;

class ChatCitationTrailer
{
    /**
     * @return array{0: string, 1: list<int>}
     */
    public static function split(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (preg_match('/(?:^|\s)CITES:\s*([^\n]*)(?:\n[\s\S]*)?\s*\z/', $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [self::hideIncompleteTrailer($text), []];
        }

        $body = trim(substr($text, 0, (int) $matches[0][1]));
        $raw = strtolower(trim($matches[1][0]));

        if ($raw === '' || $raw === 'none') {
            return [$body, []];
        }

        $ids = [];

        foreach (preg_split('/\s*,\s*/', $matches[1][0]) ?: [] as $part) {
            $part = trim($part, " \t.");

            if (is_numeric($part)) {
                $ids[] = (int) $part;
            }
        }

        return [$body, array_values(array_unique($ids))];
    }

    public static function visible(string $text): string
    {
        [$body] = self::split($text);

        return $body;
    }

    private static function hideIncompleteTrailer(string $text): string
    {
        $stripped = preg_replace('/(?:^|\s)CI(?:T(?:E(?:S(?::[^\n]*)?)?)?)?\s*\z/', '', $text);

        return trim((string) ($stripped ?? $text));
    }
}
