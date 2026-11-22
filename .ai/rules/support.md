---
paths:
  - 'app/Support/Chat*.php'
  - app/Support/CitedChunkIds.php
  - app/Support/CitedSources.php
  - app/Support/ChatFollowUpQuery.php
  - app/Support/DocumentTextExtractor.php
---

# Support

## Never render raw CITES markers
Strip CITES trailers from visible chat answers even when the model puts CITES: on the same line as the last sentence. Parse IDs from that marker, hide incomplete CITES prefixes while streaming, and run ChatAnswerHtml through ChatCitationTrailer::visible so stored leaks never render. Keep Source: citations working from the parsed IDs.

## Is-that follow-ups reuse prior subjects
Treat short Is/was/does/can/will that|it follow-ups as contextual so retrieval prepends the previous non-gated user turn even when the follow-up alone returns hits. Keep new topical questions current-query only. Never prefix with a ChatInjectionGate-blocked turn.

## Paraphrases still need the stating chunk
CitedChunkIds::usesChunk falls back to shared grounded claims (free/no charge plus 30 days) when 5-grams miss. CitedSources::groupByArticle sets includes_intro for null headings so the article title remains the locator for intro claims; keep subsection headings visible.

## Underspecified follow-ups reuse the previous subject
A follow-up of at most three words with no topic of its own, such as “time” or “what time”, reuses the previous non-gated user turn even when the short text retrieves a different passage. A named subject such as “warranty”, and any longer self-contained question, stays on the current text only. Never prefix a ChatInjectionGate-blocked turn.

## Underspecified follow-ups keep the previous citations
When a follow-up is contextual, ChatFollowUpPassages merges the immediately previous assistant message's cited chunks ahead of the new search, still within the retrieval limit. A gap with no citations does not pull an older subject forward. An explicit new question stays current-query only. Never prefix a ChatInjectionGate-blocked turn.

## Parser subprocess output must ignore parent queue quiet mode
Forge's existing queue:work uses --quiet, which sets SHELL_VERBOSITY=-1 and otherwise suppresses the child Artisan command's JSON response. Set SHELL_VERBOSITY=0 only in the parser subprocess environment; preserve parent worker flags and parser time/memory limits. Keep the real-subprocess quiet-worker regression and verify invoice/document uploads on Forge after parser changes.

## Group adjacent supported claims at their end
Harbor and embedded Document Chat place a shared passage citation after the last consecutive supported sentence or list item, including line breaks. Distinct sources remain attached to their own claims; an intervening unsupported or differently sourced claim ends a group. Preserve complete time abbreviations and the same saved/completed-stream rendering. Citation matching is a placement heuristic, not an independent truth verifier.

## Shared readable answers and content scope
Owner wants conversation-style formatting across Ask Lucid, Customer chat and Document chat: short paragraphs, bold topic labels, bullets for facts, numbered actions. Preserve requested facts and material conditions even in Concise. Omission of an unrequested adjacent equipment hold is a scope choice, not automatically a defect; never conflate it with the deposit. Do not attach unrelated citations just because amounts match; preserve all genuinely supporting sources.

## Possessive summary questions request the whole document
Recognize both straight and curly apostrophe forms of 'Summarize this document’s main points' as a whole-document overview, alongside existing overview phrases. Preserve scoped readable-file retrieval, explicit filenames, ambiguity handling, corpus isolation and the bounded partial-coverage notice; do not fall back to title-only topical retrieval for this wording.

## Interpret harmless dictation slips without changing material details
Use surrounding words and relevant prior turns to understand a clear minor spelling/dictation slip. Clarify consequential ambiguity; never silently change names, amounts, dates, order numbers or negations, rewrite the displayed transcript, relax grounding/privacy or bypass confirmation. Transcription and answer-generation models have separate roles; no switch is implied by reference-app model names.

## Conversational overview wording keeps whole-document retrieval
Leading discourse words such as So/Okay/Well do not change an explicit summary request. Generic a/an document/file/report targets use the current scoped corpus and the same multi-file clarification as this document; retain explicit filenames, all-file scope, literal user text, private/readable boundaries and partial-coverage notices. Answer-model interpretation cannot recover rules omitted by retrieval.
