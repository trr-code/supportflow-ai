<?php

namespace App\Support;

class ChatInjectionGate
{
    public const REFUSAL = 'I can’t disclose or override internal instructions. If you have a product question, ask it directly or submit a support ticket and a human agent can help.';

    public const PREVIEW_REFUSAL = 'I can’t disclose or override internal instructions. Ask a question about these documents directly.';

    /**
     * @var list<string>
     */
    private const array PATTERNS = [
        '/\bignore\b.{0,40}\b(?:previous|prior|above)\b.{0,20}\binstructions\b/i',
        '/\breveal\b.{0,40}\b(?:hidden|system)\b.{0,20}\bprompts?\b/i',
        '/\b(?:hidden|system)\s+prompts?\b.{0,40}\breveal\b/i',
        '/\bdump\b.{0,20}\b(?:api\s+keys?|system\s+prompts?)\b/i',
        '/\bextract\b.{0,20}\b(?:api\s+keys?|system\s+prompts?)\b/i',
        '/\byou are now\b.{0,60}\bjailbreak/i',
        '/\byou are now\b.{0,40}\bdan\b/i',
        '/\bdan\s+mode\b/i',
        '/\bjailbreak(?:en|ed)?\s+(?:assistant|mode)\b/i',
    ];

    public static function blocks(string $question): bool
    {
        $question = trim($question);

        if ($question === '') {
            return false;
        }

        return array_any(self::PATTERNS, fn ($pattern) => preg_match($pattern, $question) === 1);
    }

    public static function isRefusal(string $body): bool
    {
        $normalized = mb_strtolower(trim($body));

        if ($normalized === '') {
            return false;
        }

        if ($normalized === mb_strtolower(self::REFUSAL)
            || $normalized === mb_strtolower(self::PREVIEW_REFUSAL)) {
            return true;
        }

        $targets = [
            'internal instruction',
            'hidden prompt',
            'system prompt',
            'hidden instruction',
        ];

        if (! array_any($targets, fn (string $needle) => str_contains($normalized, $needle))) {
            return false;
        }

        return preg_match(
            '/\b(?:can(?:not|[\'’]t)|won[\'’]t|will not|do not|don[\'’]t|refuse|not (?:disclose|reveal|override|share))\b/u',
            $normalized,
        ) === 1;
    }
}
