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

        return preg_match(
            '/\b(?:which one|which of (?:them|those|these)|which is (?:longer|shorter)|that (?:one|window|period|warranty|policy|return|time|option)|(?:the )?(?:longer|shorter) one)\b/i',
            $current,
        ) === 1;
    }
}
