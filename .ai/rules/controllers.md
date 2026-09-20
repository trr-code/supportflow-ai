---
paths:
  - app/Http/Controllers/StreamChatController.php
---

# Controllers

## Chat writes go through POST SSE
Chat validation, throttle:chat, and recordUser live only on POST chat.stream. Widget::send() is UX-only. Do not RateLimiter::hit in Livewire and do not call ChatService::ask() after recordUser. Acquire Cache::lock chat-stream:{demoSessionId} fail-fast; do not Route::block(). Named chat limiter is IP plus session. Stop stays unthrottled.
