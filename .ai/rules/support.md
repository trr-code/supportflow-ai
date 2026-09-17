---
paths:
  - 'app/Support/Chat*.php'
---

# Support

## Never render raw CITES markers
Strip CITES trailers from visible chat answers even when the model puts CITES: on the same line as the last sentence. Parse IDs from that marker, hide incomplete CITES prefixes while streaming, and run ChatAnswerHtml through ChatCitationTrailer::visible so stored leaks never render. Keep Source: citations working from the parsed IDs.
