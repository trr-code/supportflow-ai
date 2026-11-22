---
paths:
  - app/Services/DemoScenarioService.php
  - app/Services/TicketIntakeService.php
  - app/Services/SuggestedReplyService.php
  - app/Services/ChatService.php
  - app/Services/KnowledgeIndexService.php
  - app/Services/RetrievalService.php
  - app/Services/SupplierInvoiceExport.php
  - 'app/Services/**'
  - 'app/Services/AskLucid*.php'
  - app/Services/HarborChatHandoff.php
---

# Services

## Scenario catalog copy stays client-facing
Visitor-facing Agent scenario cards use label, situation, ai, and expect. Write for a non-technical client. Never use fixture, seeded, mutated, ground, retrieval, triage, synthetic, skip-draft, or Look for. Keep all nine catalog keys and launch behavior.

## Angry tickets escalate without a draft
After triage, Angry sentiment escalates to a human and skips GenerateSuggestedReply. Raise priority to High unless it is already Urgent. Do not skip drafts for generic needs_human.

## Awaiting review is set with the draft
Create the pending suggested reply and set ticket status to Awaiting review in one transaction so a poll cannot show a finished draft under AI reviewing.

## Regenerate must differ or keep the draft
Regenerate must pass the previous pending draft into the prompt, require different wording or structure, keep the same grounded facts and sources, and add no unsupported information. If the formatted body is identical, retry once on the same AiRun. If it is still identical, keep the current pending draft, complete the run with unchanged true, and record SuggestionRegenerated instead of creating a duplicate.

## Anaphora retrieval fallback skips injection turns
If current-question search is empty, retry RetrievalService::search() with the previous non-gated user turn plus the current question. Never prefix anaphora search with a ChatInjectionGate-blocked turn. Stream via SupportChatStreamAgent::make(conversation: $conversation).

## Queued embeddings must not also embed synchronously
syncArticle embeds synchronously XOR dispatches EmbedKnowledgeChunk. When queueEmbeddings is true, persist null embeddings and let the job fill them. Never embed in both places. Seeding stays queueEmbeddings: false.

## Contextual follow-ups reuse previous subjects
Contextual follow-ups such as “Which one is longer?” must retrieve with the previous non-gated user turn plus the current question even when the follow-up alone returns hits. New topical questions stay current-query only. Never prefix retrieval with a ChatInjectionGate-blocked turn. Citations still come only from the current turn’s allowed chunk IDs.

## Paraphrased claims still cite the stating passage
5-word overlap is not enough to decide citations. If the draft asserts a claim the retrieved passage actually states (for example free and 30 days), CitedChunkIds::usedInBody must add that chunk even when the model paraphrased it. Subsection headings such as How to start must not stand in for an uncited article intro. Do not change seeded knowledge merely to match a generated paraphrase.

## OR expansion skips brand and order tokens
OR-expanded lexical search must not rank on Harbor, Outfitters, order, or orders. Those tokens appear across the seeded catalog and make unknown questions retrieve unrelated policy. Distinct fake embeddings belong in retrieval Feature tests; never assign one shared vector when ranking is under test.

## Stop force-releases the chat stream lock
Stop and new conversation must interruptStream: requestStop, bump chat-gen, and forceRelease chat-stream:{session}. Aborting the browser fetch does not release the SSE lock. In-flight ask must not write if the generation no longer matches.

## Pass a knowledge corpus into retrieval
RetrievalService::search() requires a KnowledgeCorpus. Harbor is workspace-null, published, and seeded. A workspace corpus is that workspace id plus a ready workspace document. Do not search published chunks without a corpus, or client text can enter Harbor chat and ticket drafts.

## Follow-up retrieval keeps the previous citations
Contextual follow-ups still search the previous non-gated user turn plus the current question. ChatFollowUpPassages then puts the immediately previous assistant message's cited chunks ahead of those hits. Do not merge citations for a self-contained new question, and do not walk back past a gap that cited nothing.

## Batch retrieval embeddings in one generate()
RetrievalService::search() must embed the visitor query plus matching topic/facet catalog strings in one Embeddings::for()->generate() call (embeddingsFor/embedMany). Sequential embed() round-trips sit on the Octane worker before the first chat delta. Do not add Forge Octane workers to chase concurrent chat TTFB; 1 worker still serializes overlapping POST /chat/stream.

## Invoice CSV declares UTF-8 for Excel and keeps formula protection
Invoice CSV starts with one UTF-8 BOM so Excel can recognize non-ASCII supplier/line text on opening. Preserve the existing protective tab on formula-like text and RFC-style CSV quoting, readable decimal amounts and JSON integer cents. Test the actual download route with multilingual text, a full-width formula prefix, quotes/commas and JSON fidelity. CSV consumers must account for the BOM before reading the first header; do not substitute page styling for spreadsheet acceptance.

## Shared workflow services
Keep reusable workflow decisions in named services called by controllers, Livewire pages, and jobs.

## Session-owned workflow records
Reuse the owning service to initialize chat conversations and replacement cases so each workflow keeps its existing ownership scope.

## Document overviews use scoped readable passages
Broad requests such as “What’s this about?” and “Summarize this document” must use bounded overview passages, not depend on topical similarity. Preserve KnowledgeCorpus isolation and citations; clarify an ambiguous singular request with multiple uploads, and disclose sampled coverage. Blank/image-only files are not missing-answer cases: explain their reading status without claiming OCR or knowing an empty PDF is definitely blank.

## Ask Lucid explains verified product behavior without private workflow access
Ask Lucid is the Lucid product guide, separate from Harbor policies and private Document chat. Maintain its curated product knowledge against actual controls/config; do not feed internal docs, uploaded passages, ticket content, secrets or user-supplied system/history roles. Browser-session history is bounded and expires; Stop/New chat invalidate in-flight generations so late streams cannot restore cleared history. Navigation links are app-supplied landmarks, not evidence citations. Preserve the accepted launcher geometry and shared role bubbles.

## Restore interrupted Ask Lucid with reachable Stop
A browser abort or refresh can leave the SSE lock active. History restoration must expose active-answer state and the panel must offer Stop after restoration or a 409. Do not auto-cancel another tab on read. Stop preserves completed history and invalidates late writes.

## Ask Lucid Stop preserves the visible turn
Ask Lucid shares paced text reveal with the other chats. Stop retains the question and revealed server-generated prefix marked Stopped, with neutral feedback. Never accept assistant content from the browser; validate the generation and bound the character count. Reset still invalidates late writes and clears only Ask Lucid.

## Confirm collected return requests in chat
Higher-priced return requests retain collected details for explicit Send support request confirmation directly in chat, then link to the customer status. Use session-owned ticket services and idempotent return-review creation. Never treat typed order details as verified order lookup or refund approval, and never expose private staff drafts or notes.

## Reply status names the next actor
Sending a reply does not necessarily mean waiting on the customer. Agent reply UI explicitly chooses Awaiting staff review, Waiting on customer or Resolved. Unspecified service return-review replies default to staff review. Staff-pending public status keeps polling after an acknowledgement so the eventual reply appears. Keep existing authorization, approved-public-message boundaries and event history.

## Document Stop retains server-owned visible text
Document streaming emits its user-turn ID before deltas and snapshots server-generated text. Stop accepts only an owned current turn plus a bounded revealed-character count, never browser-authored answer text; it persists the visible prefix with is_stopped and no citations, invalidates the stream generation and releases its lock. Serialize final persistence with Stop; stale generations must not recreate deleted turns or modify a newer conversation.

## Overview follow-ups retain their bounded overview context
For contextual detail requests following a document overview, retrieve the previous overview request intact rather than concatenating a second sentence that defeats summary recognition; keep the existing bounded passage set. Ignore stopped attempts when resolving the previous meaningful subject and citations, but never walk past a completed uncited gap. An underspecified first turn asks for a brief subject clarification. Maintain corpus isolation and allowed citation IDs.

## Eloquent workflow services are the default
Keep workflow queries and persistence in the existing named services using Eloquent directly. Do not introduce a repository abstraction solely for consistency; a different structure requires a concrete documented need. This is a project default, not a Laravel prohibition on repositories.
