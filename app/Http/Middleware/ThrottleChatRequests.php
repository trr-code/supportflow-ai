<?php

namespace App\Http\Middleware;

use Illuminate\Cache\RateLimiter;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;

class ThrottleChatRequests extends ThrottleRequests
{
    public function __construct(RateLimiter $limiter)
    {
        $store = config('supportflow.rate_limits.chat_store');
        $policy = $limiter->limiter('chat');

        if (is_string($store) && $store !== '' && $policy !== null) {
            $limiter = (new RateLimiter(Cache::store($store)))->for('chat', $policy);
        }

        parent::__construct($limiter);
    }
}
