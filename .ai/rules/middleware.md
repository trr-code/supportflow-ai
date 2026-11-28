---
paths:
  - app/Http/Middleware/ThrottleChatRequests.php
---

# Middleware

## Keep the optional Redis chat limiter scoped to sends
The local Redis override applies only to the Harbor and Ask Lucid chat POSTs and keeps their shared IP/session policy. Livewire 4 also uses Laravel's default RateLimiter for its checksum failure guard, so moving the global limiter to the optional Redis service prevents Stop and New chat during a Redis outage. Preserve framework checksum protection and keep cancellation state and locks on the existing cache until a separate failure-mode test proves a safe replacement.
