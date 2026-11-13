<?php

namespace App\Exceptions;

use RuntimeException;

class DemoCapReachedException extends RuntimeException
{
    public static function perSession(): self
    {
        $limit = (int) config('supportflow.demo.max_tickets_per_session');
        $minutes = (int) config('supportflow.demo.stale_minutes');

        return new self("This demo visit has reached its {$limit}-ticket limit. You can still review existing tickets. A new visit starts after {$minutes} minutes of inactivity.");
    }

    public static function global(): self
    {
        return new self('The demo is at capacity. You can still review an existing ticket or try submitting again later.');
    }
}
