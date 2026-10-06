<?php

namespace App\Support;

class ChatFollowUpQuery
{
    public static function needsPreviousSubjects(string $current, string $previous): bool
    {
        if (trim($current) === '' || trim($previous) === '') {
            return false;
        }

        if (ChatInjectionGate::blocks($current) || ChatInjectionGate::blocks($previous)) {
            return false;
        }

        return self::isContextual($current);
    }

    public static function retrievalQuery(string $current, ?string $previous): string
    {
        if ($previous === null || ! self::needsPreviousSubjects($current, $previous)) {
            return $current;
        }

        return $previous."\n".$current;
    }

    private static function isContextual(string $current): bool
    {
        if (preg_match('/^\s*(?:so[,:]?\s+)?(?:and\s+)?(?:which(?: one)? is |what(?:\'s| is) )?(?:longer|shorter)\??\s*$/i', $current) === 1) {
            return true;
        }

        if (preg_match('/^\s*(?:so[,:]?\s+)?(?:and\s+)?(?:is|was|does|can|will)\s+(?:that|it)\b/i', $current) === 1) {
            return true;
        }

        if (preg_match(
            '/\b(?:which one|which of (?:them|those|these)|which is (?:longer|shorter)|that (?:one|window|period|warranty|policy|return|time|option)|(?:the )?(?:longer|shorter) one)\b/i',
            $current,
        ) === 1) {
            return true;
        }

        return self::isUnderspecified($current);
    }

    /**
     * A short question with no topic of its own continues the previous turn.
     * A named subject such as warranty stays a new question.
     */
    private static function isUnderspecified(string $current): bool
    {
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($current)) ?? '';
        $tokens = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === [] || count($tokens) > 3) {
            return false;
        }

        $withoutSubject = [
            'a', 'an', 'the', 'to', 'of', 'for', 'and', 'or', 'on', 'in', 'at', 'my', 'your', 'our',
            'me', 'it', 'that', 'this', 'what', 'when', 'where', 'why', 'who', 'how', 'which',
            'time', 'times', 'hour', 'hours', 'day', 'days', 'cost', 'costs', 'price', 'prices',
            'much', 'long', 'else', 'about', 'please', 'tell',
        ];

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                continue;
            }

            if (! in_array($token, $withoutSubject, true)) {
                return false;
            }
        }

        return true;
    }
}
