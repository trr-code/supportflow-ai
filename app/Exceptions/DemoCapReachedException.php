<?php

namespace App\Exceptions;

use RuntimeException;

class DemoCapReachedException extends RuntimeException
{
    public static function perSession(): self
    {
        return new self('This demo session has reached the ticket limit. Open the agent dashboard or wait for stale data to prune.');
    }

    public static function global(): self
    {
        return new self('The shared demo is at capacity. Try again after stale visitor tickets are pruned.');
    }
}
