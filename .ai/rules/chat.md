---
paths:
  - app/Livewire/Chat/Widget.php
---

# Chat

## Prepared chat fills never reset the thread
Safety and suggested-question buttons only open chat and fill the composer. They must not send and must not call startNewConversation. The thread resets only from New conversation. Instruction-override replies still store empty cited_chunk_ids; earlier answers keep the sources they already cited.
