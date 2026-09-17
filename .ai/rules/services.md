---
paths:
  - app/Services/DemoScenarioService.php
  - app/Services/TicketIntakeService.php
  - app/Services/SuggestedReplyService.php
  - app/Services/KnowledgeIndexService.php
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

## Queued embeddings must not also embed synchronously
syncArticle embeds synchronously XOR dispatches EmbedKnowledgeChunk. When queueEmbeddings is true, persist null embeddings and let the job fill them. Never embed in both places. Seeding stays queueEmbeddings: false.
