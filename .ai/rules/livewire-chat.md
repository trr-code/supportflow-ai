---
paths:
  - resources/views/livewire/chat/widget.blade.php
---

# Livewire Chat

## Pin the live overflow transcript, not Alpine refs
The scrollable element is `[data-chat-transcript]` (`min-h-0 flex-1 overflow-y-auto`). Query it from `$root` after morph. Submit (`x-on:submit="pinNewest()"`) snaps once. After that, send/fetch morph, `liveHtml`, and `$wire.streaming` may only call `scrollTranscript()` while `pinToBottom` is still true—never set `pinToBottom = true`. Unpin immediately on an upward wheel, or on touch/pointer that leaves the bottom by more than 24px. A downward scroll that reaches the end sets pinToBottom again; do not resume on an upward scroll. Do not add `@script`.

## Center the mobile chat panel
Below md, pin the widget with `start-4 end-4` so both side margins stay equal and the panel cannot overflow the viewport. Do not combine `end-4` with `w-full max-w-sm` on that width—that clips the start edge. Keep the panel `w-full min-w-0 overflow-hidden`. From md, use the shared bottom-right dock instead of `sm:max-w-sm`.

## Chat tokens stream over fetch SSE
Token paint is Alpine fetch of chat.stream plus AbortController, not wire:stream or completeTurn. send/fetch morph, liveHtml, and $wire.streaming may only call scrollTranscript() while pinToBottom is true. Stop aborts the fetch and calls stopGenerating.

## Done events paint sources before morph
The SSE done payload includes html and sources. Hold sources until the visible reveal catches that HTML, then finishTurn, then clear the live fields. Never blank liveHtml while $wire.streaming is still true—that flashes Thinking… and jumps the Source: list in after morph. A stop before done still clears without sources. failOpenStream and startChatStream still reset liveSources.

## Dropped streams recover without waiting on Livewire
Chrome DevTools Offline does not fire window offline/online and leaves fetch/reader.read() pending. Recover with readWithStall (Promise.race, 20s) and a static robots.txt probe (not GET /up—Octane 1 worker is busy streaming). failOpenStream aborts SSE and sets streamFailed immediately so Thinking… and the disabled composer do not wait on Livewire. commitLivewireRecovery try/catches abandonFailedStream and retries on @online.window plus a 1s timer because that /livewire/update cannot succeed while Offline and is not retried otherwise. abortReason=network still prevents a second SSE. Stop sets abortReason=stop then aborts; AbortError must not show the stream-error message. 409 conflict still uses reportStreamError so it does not forceRelease another stream's lock. Do not add @script.

## Share the bottom-right chat size from md
Below md, keep Harbor full width with start-4 and end-4 and no Expand control. From md, chat-dock is the same bottom-right size as Preview: a wider expanded panel still anchored to that corner, inside the viewport. Do not use sm:max-w-sm for the open panel.

## Re-pin a transcript when a downward scroll reaches the end
On a downward wheel, set scrollingDown and clear ignoreScroll. The following scroll event re-pins when it is within 96px of the end or already at the 24px clamp, then follows. An upward wheel clears the pin immediately. The animation frame inside scrollTranscript must not assign scrollTop once pinToBottom is false. Do not pin on morph or on an upward scroll.

## Resolve the transcript from the component root
transcriptEl() must start from this.$root, not this.$el. Wheel and scroll handlers run with $el set to the transcript, and querySelector does not match the element it starts from, so a $el lookup returns null and the return-to-bottom latch never runs. The same lookup belongs on the Preview transcript.

## Pace Harbor the same way as Preview
Harbor uses Preview's visible pace: store server HTML in pacedTarget and reveal about two characters every 32ms into liveHtml. Hold sources until that reveal catches done, then finishTurn. Stop clears the timer. If done has already arrived, show the final HTML and sources at once. If it has not, abort and do not reveal the unseen target. Do not sleep in PHP.
