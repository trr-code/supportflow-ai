<?php

namespace App\Support;

final class ChatSummaryQuery
{
    public const string PARTIAL_NOTICE = 'This overview uses selected passages and may omit parts of the documents. Ask about a specific section for more detail.';

    public static function target(string $question): ?string
    {
        $question = mb_strtolower(trim(str_replace('’', "'", $question)));
        $question = preg_replace('/^(?:(?:ok(?:ay)?|so|well)(?:\s*[,;:]\s*|\s+))+/u', '', $question) ?? $question;
        $question = preg_replace('/^(?:please\s+)?(?:(?:can|could|would) you\s+(?:please\s+)?)?/u', '', $question) ?? $question;
        $question = rtrim($question, " \t\n\r\0\x0B?.!");
        $patterns = [
            '/^what(?:\'s| is) (.+?)(?: all)? about$/u',
            '/^what(?:\'s| is) (?:in|inside) (.+)$/u',
            '/^what does (.+?) (?:say|contain|cover)$/u',
            '/^tell me what (.+?) (?:is|are) about$/u',
            '/^tell me about (.+)$/u',
            '/^(?:summ[ae]ri[sz]e|give me (?:a summary|an overview) of|(?:explain|describe) (?:the )?(?:main|key) (?:points|ideas) (?:in|of|from)) (.+)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $question, $match) !== 1) {
                continue;
            }

            $target = preg_replace('/^(?:the )?(?:main|key) (?:points|ideas|takeaways)(?: in| of| from)?\s+/u', '', $match[1]) ?? $match[1];
            $target = preg_replace('/(?:\x27s)?\s+(?:main|key)\s+(?:points|ideas|takeaways)$/u', '', $target) ?? $target;
            $target = trim($target, " \t\n\r\"'");
            $target = preg_replace('/^this (document|file|pdf|report|upload)s$/u', 'this $1', $target) ?? $target;

            if (preg_match('/^(?:(?:all|these|this|the|my|uploaded|attached)\s+)*(?:documents|files|policies|uploads|reports|pdfs)$/u', $target) === 1) {
                return '*';
            }

            if (preg_match('/^(?:this|it|(?:(?:a|an|this|the|my|uploaded|attached)\s+)*(?:document|file|pdf|report|upload|text))$/u', $target) === 1) {
                return '';
            }

            return $target;
        }

        return null;
    }
}
