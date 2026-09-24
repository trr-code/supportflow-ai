---
paths:
  - app/Http/Controllers/StreamChatController.php
---

# Controllers

## Chat writes go through POST SSE
Chat validation, throttle:chat, and recordUser live only on POST chat.stream. Widget::send() is UX-only. Do not RateLimiter::hit in Livewire and do not call ChatService::ask() after recordUser. Acquire Cache::lock chat-stream:{demoSessionId} fail-fast; do not Route::block(). Stop and new conversation forceRelease that lock so a follow-up POST is not 409 Please wait. Named chat limiter is IP plus session. Stop stays unthrottled.

## Done SSE includes citation labels
done payloads include id, html, and sources from CitedSources::streamLabels. stopped payloads stay {id} with no sources. Do not wait for Livewire morph to introduce Source: lines.
