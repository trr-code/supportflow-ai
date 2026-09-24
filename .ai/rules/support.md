---
paths:
  - 'app/Support/Chat*.php'
---

# Support

## Never render raw CITES markers
Strip CITES trailers from visible chat answers even when the model puts CITES: on the same line as the last sentence. Parse IDs from that marker, hide incomplete CITES prefixes while streaming, and run ChatAnswerHtml through ChatCitationTrailer::visible so stored leaks never render. Keep Source: citations working from the parsed IDs.

## Is-that follow-ups reuse prior subjects
Treat short Is/was/does/can/will that|it follow-ups as contextual so retrieval prepends the previous non-gated user turn even when the follow-up alone returns hits. Keep new topical questions current-query only. Never prefix with a ChatInjectionGate-blocked turn.
