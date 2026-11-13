---
paths:
  - app/Http/Controllers/StreamChatController.php
  - 'app/Http/Controllers/**'
---

# Controllers

## Chat writes go through POST SSE
Chat validation, throttle:chat, and recordUser live only on POST chat.stream. Widget::send() is UX-only. Do not RateLimiter::hit in Livewire and do not call ChatService::ask() after recordUser. Acquire Cache::lock chat-stream:{demoSessionId} fail-fast; do not Route::block(). Stop and new conversation forceRelease that lock so a follow-up POST is not 409 Please wait. Named chat limiter is IP plus session. Stop stays unthrottled.

## Done SSE includes citation labels
done payloads include id, html, and sources from CitedSources::streamLabels. stopped payloads stay {id} with no sources. Do not wait for Livewire morph to introduce Source: lines.

## Single-purpose HTTP controllers
Use invokable controllers for single-purpose endpoints. Keep related export formats together in one controller and delegate shared workflow logic to services.
