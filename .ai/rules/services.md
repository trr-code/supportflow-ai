---
paths:
  - app/Services/DemoScenarioService.php
  - app/Services/TicketIntakeService.php
  - app/Services/SuggestedReplyService.php
  - app/Services/ChatService.php
  - app/Services/KnowledgeIndexService.php
  - app/Services/RetrievalService.php
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
