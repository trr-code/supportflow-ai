---
paths:
  - app/Livewire/Chat/Widget.php
---

# Chat

## Prepared chat fills never reset the thread
Safety and suggested-question buttons only open chat and fill the composer. They must not send and must not call startNewConversation. The thread resets only from New conversation. Instruction-override replies still store empty cited_chunk_ids; earlier answers keep the sources they already cited.

## Stop interrupts the stream lock
stopGenerating must call ChatService::interruptStream, not only requestStop. That force-releases the SSE lock so the composer can send again after Stopped. startNewConversation also interrupts via ChatService.

## Failed streams interrupt; 409 does not
abandonFailedStream interruptStream then restores the composer and is safe to call twice after a dropped Livewire update. reportStreamError is UI-only so a 409 concurrent stream does not steal the lock. Stop still uses stopGenerating and records Stopped.
