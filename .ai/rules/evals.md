---
paths:
  - 'tests/Evals/**'
---

# Evals

## Evals wrap live services locally
Pest Evals wrap ChatService, TicketIntakeService, SuggestedReplyService, and ReplacementCaseService against KnowledgeSeeder. Do not prompt SupportChatStreamAgent without retrieval. The replacement eval prompts ReplacementAgent for real and asserts the lookups. Skip unless --evals. Keep them out of composer test and GitHub Actions. Do not fake embeddings or agents. OPENAI_SMOKE stays until triage evals cover it.

## Do not skip live evals for API cost
OpenAI API cost is not a reason to skip Pest evals or live-AI showroom checks. Do not re-run a passing eval or an already-observed live turn. Keep Forge and CareerForge Stressless off this machine except off-peak when requested.

## Preview bakeoff stays one-file three-heading
Keep the Preview bakeoff corpus as one file with Shipping, Returns, and Warranty headings. Do not skip evals for API cost. Workspace retrieval may raise the per-article cap so that one-file case returns all three headings; Harbor stays at catalog diversity of two.
