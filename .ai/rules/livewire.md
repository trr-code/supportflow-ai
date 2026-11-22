---
paths:
  - 'resources/views/livewire/**'
---

# Livewire

## Consume the final chat stream frame
Harbor and Document chat readers must flush TextDecoder and consume the remaining SSE frame at EOF before treating a stream as interrupted. Buffered/test responses can omit the trailing blank delimiter; a saved deterministic answer must not also restore its question and show a false stream error. Preserve failure recovery for genuinely missing terminal events.
