<?php

namespace App\Support;

class ChatAnswerCopy
{
    /**
     * Clarify a few documented phrases the model may copy too narrowly or too technically.
     */
    public static function normalize(string $body): string
    {
        $body = self::clarifyAppRefresh($body);
        $body = self::clarifyFuelCanisters($body);
        $body = self::clarifyOrderLookup($body);
        $body = self::clarifyPrivacyHedge($body);

        return $body;
    }

    protected static function clarifyAppRefresh(string $body): string
    {
        $clear = 'On the tracking screen, swipe downward and release to refresh the latest information';
        $body = preg_replace(
            '/Pull to refresh in the Harbor(?:\s+iOS or Android)? app\.?/iu',
            $clear.'.',
            $body,
        ) ?? $body;

        return preg_replace('/\bPull to refresh\b/iu', $clear, $body) ?? $body;
    }

    protected static function clarifyFuelCanisters(string $body): string
    {
        $body = preg_replace(
            '/We do not currently ship tents with fuel canisters\.?/iu',
            'We do not ship fuel canisters to Canada.',
            $body,
        ) ?? $body;

        return preg_replace(
            '/We do not ship tents with fuel canisters\.?/iu',
            'We do not ship fuel canisters to Canada.',
            $body,
        ) ?? $body;
    }

    protected static function clarifyOrderLookup(string $body): string
    {
        return preg_replace(
            '/I (?:can(?:not|[\'’]t)|could not) look up.{0,120}from the provided information\.?/iu',
            'This demo cannot access real order records.',
            $body,
        ) ?? $body;
    }

    protected static function clarifyPrivacyHedge(string $body): string
    {
        return preg_replace(
            '/I (?:can(?:not|[\'’]t)|could not) determine whether customer or order information (?:is|was) real\.?/iu',
            'This portfolio demo uses no real customers, orders, or payments. Enter invented information only.',
            $body,
        ) ?? $body;
    }
}
