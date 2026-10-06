<?php

namespace App\Exceptions;

use RuntimeException;

class DemoCapReachedException extends RuntimeException
{
    public static function perSession(): self
    {
        $limit = (int) config('supportflow.demo.max_tickets_per_session');
        $minutes = (int) config('supportflow.demo.stale_minutes');

        return new self("This browser can submit {$limit} tickets. Prepared examples count, and tickets already submitted in this browser count. They are removed after this browser has been idle for {$minutes} minutes. Keep using a ticket already submitted, or open a private window to submit another now.");
    }

    public static function global(): self
    {
        return new self('The shared demo is full. Other visitors’ tickets are cleaned up on a schedule. Opening a private window does not reset that shared limit.');
    }
}
