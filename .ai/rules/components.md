---
paths:
  - resources/views/components/dictation-button.blade.php
---

# Components

## Dictation stays local and does not fade the field
Live transcription stays in this shared button. Paint the field locally and sync with $wire.set(name, value, false); do not send a server update on every delta. Replace only an unedited in-progress draft with the final transcript so typed text and edits remain. A native textarea cannot fade individual words; do not fade the whole field or replace it with an editor. prefers-reduced-motion keeps the level bars still.

## Commit dictation turns before closing
gpt-live-transcribe does not detect turns. Send input_audio_buffer.commit after a pause and again on stop, and keep the peer connection open until each commit's completed transcript arrives. The completed transcript replaces that item's partial text. Only a trusted input event is a user edit; keep that correction and still append later items. Do not close the data channel on the stop click itself.
