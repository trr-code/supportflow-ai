<?php

namespace App\Support;

class ChatInjectionGate
{
    public const REFUSAL = 'I can’t disclose or override internal instructions. If you have a product question, ask it directly or submit a support ticket and a human agent can help.';

    /**
     * @var list<string>
     */
    private const PATTERNS = [
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

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $question) === 1) {
                return true;
            }
        }

        return false;
    }
}
