---
paths:
  - 'resources/js/**'
---

# Js

## Shared dictation follows the current microphone contract
Before changing dictation.js or its integration in app.js, read .ai/rules/components.md and the current readiness checkpoint. The recorder is independent of mounted Alpine views so wire:navigate/focus changes preserve capture and original-draft ownership. First empty capture uses a full composer waveform; resumed capture adds live text above it. Explicit Send/reset must clear resumable state; late finals cannot restore submitted/deleted text. Keep resource handles outside reactive proxies, listeners cleaned up, browser unload cleanup, manual commits and bounded recording/finalization. Official OpenAI API mechanics do not mandate a cross-page banner or auto-stop; latest owner decisions govern UX.

## Finalize chat dictation only for an explicit Send
Customer chat, Document chat and Ask Lucid opt in to Send stopping capture and awaiting a successful final transcript before one submission; Stop only reviews. Repeated finalizing sends, failed/empty transcription, reset or a detached/replaced originating composer must not submit. Non-chat forms and instruction Save controls remain review-first. Keep quiet accessible progress, visible actionable errors and original-draft ownership.
