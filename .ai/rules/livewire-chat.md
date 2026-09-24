---
paths:
  - resources/views/livewire/chat/widget.blade.php
---

# Livewire Chat

## Pin the live overflow transcript, not Alpine refs
The scrollable element is `[data-chat-transcript]` (`min-h-0 flex-1 overflow-y-auto`). Query it from `$el` after morph. Submit (`x-on:submit="pinNewest()"`) snaps once. After that, send/fetch morph, `liveHtml`, and `$wire.streaming` may only call `scrollTranscript()` while `pinToBottom` is still true—never set `pinToBottom = true`. Unpin immediately on an upward wheel, or on touch/pointer that leaves the bottom by more than 24px. Resume only when the visitor scrolls back within 24px or submits another question. Do not add `@script`.

## Center the mobile chat panel
On small screens pin the widget with `start-4 end-4` so both side margins stay equal and the panel cannot overflow the viewport. Do not combine `end-4` with `w-full max-w-sm` below `sm`—that clips the start edge. From `sm`, use `start-auto sm:w-full sm:max-w-sm` and keep `end-4`. Keep the panel `w-full min-w-0 overflow-hidden`.

## Chat tokens stream over fetch SSE
Token paint is Alpine fetch of chat.stream plus AbortController, not wire:stream or completeTurn. send/fetch morph, liveHtml, and $wire.streaming may only call scrollTranscript() while pinToBottom is true. Stop aborts the fetch and calls stopGenerating.

## Done events paint sources before morph
The SSE done payload includes html and sources. Alpine must assign liveHtml and liveSources, then finishTurn, then clear those live fields. Never blank liveHtml while $wire.streaming is still true—that flashes Thinking… and jumps the Source: list in after morph. Stopped still clears immediately and sends no sources. failOpenStream and startChatStream still reset liveSources.

## Dropped streams recover without waiting on Livewire
Chrome DevTools Offline does not fire window offline/online and leaves fetch/reader.read() pending. Recover with readWithStall (Promise.race, 20s) and a static robots.txt probe (not GET /up—Octane 1 worker is busy streaming). failOpenStream aborts SSE and sets streamFailed immediately so Thinking… and the disabled composer do not wait on Livewire. commitLivewireRecovery try/catches abandonFailedStream and retries on @online.window plus a 1s timer because that /livewire/update cannot succeed while Offline and is not retried otherwise. abortReason=network still prevents a second SSE. Stop sets abortReason=stop then aborts; AbortError must not show the stream-error message. 409 conflict still uses reportStreamError so it does not forceRelease another stream's lock. Do not add @script.
