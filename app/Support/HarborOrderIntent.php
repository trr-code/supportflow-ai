<?php

namespace App\Support;

class HarborOrderIntent
{
    public static function matches(string $question): bool
    {
        if (preg_match('/\bHB-\d{3,}\b/i', $question) === 1) {
            return true;
        }

        return preg_match('/\b(replacement|replace (it|my|the)|look up (my |the )?order|order number|check stock)\b/i', $question) === 1;
    }
}
