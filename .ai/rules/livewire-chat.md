---
paths:
  - resources/views/livewire/chat/widget.blade.php
---

# Livewire Chat

## Pin the live overflow transcript, not Alpine refs
The scrollable element is `[data-chat-transcript]` (`min-h-0 flex-1 overflow-y-auto`), not the window or Alpine `$refs`. Query it from `$el` after every Livewire morph—`$refs` and `scrollIntoView` miss the live node or scroll the wrong ancestor. Set `scrollTop = scrollHeight` on submit (`x-on:submit="pinNewest()"`), on send/completeTurn `onSend`/`onMorphed`/`onRender`/`onFinish`, on `onStream`, and via MutationObserver/ResizeObserver while pinned. Unpin only after a wheel/touch/pointer gesture leaves the bottom by more than 24px; morph restoring `scrollTop` must re-pin, not clear `pinToBottom`. A new question sets `pinToBottom` again. Do not add `@script`.
