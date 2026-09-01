<?php

namespace App\Support;

class SuggestedReplyCopy
{
    /**
     * Layout a customer-facing draft: one personalized greeting, body, professional closing.
     */
    public static function format(string $body, string $customerName, ?string $agentName = null): string
    {
        $first = self::firstName($customerName, 'there');
        $text = self::stripLeadingGreetings(self::stripTrailingClosings(trim($body)));

        return "Hi {$first},\n\n{$text}\n\n".self::closing($agentName);
    }

    public static function closing(?string $agentName = null): string
    {
        $agent = trim($agentName ?? (string) config('supportflow.brand.agent_name'));
        $agent = $agent !== '' ? $agent : 'Alex Rivera';

        return "Best,\n{$agent}\nHarbor & Co Support";
    }

    public static function firstName(string $name, string $fallback): string
    {
        $first = trim(explode(' ', trim($name), 2)[0]);

        return $first !== '' ? $first : $fallback;
    }

    protected static function stripTrailingClosings(string $text): string
    {
        $previous = null;

        while ($previous !== $text) {
            $previous = $text;
            $text = preg_replace('/(?:\r?\n)*—\s*Alex(?:\s+Rivera)?\s+at Harbor & Co\s*$/u', '', $text) ?? $text;
            $text = preg_replace('/(?:\r?\n)*Best,?\s*\n\s*Alex(?:\s+Rivera)?\s*(?:\n\s*Harbor & Co Support)?\s*$/u', '', $text) ?? $text;
            $text = trim($text);
        }

        return $text;
    }

    protected static function stripLeadingGreetings(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $previous = null;

        while ($previous !== $text) {
            $previous = $text;
            $text = preg_replace('/^Hi\s*,\s*/u', '', ltrim($text)) ?? $text;
            $text = preg_replace('/^Hello\s*,\s*/u', '', ltrim($text)) ?? $text;
            $text = preg_replace('/^Hi\s+[^\n,]{1,40},\s*/u', '', ltrim($text)) ?? $text;
            $text = preg_replace('/^Hello\s+[^\n,]{1,40},\s*/u', '', ltrim($text)) ?? $text;
            $text = preg_replace('/^Hi\s+[^\n—,]{1,40}\s*[—]\s*/u', '', ltrim($text)) ?? $text;
            $text = preg_replace('/^Hello\s+[^\n—,]{1,40}\s*[—]\s*/u', '', ltrim($text)) ?? $text;
            $text = ltrim($text);
        }

        return trim($text);
    }
}
