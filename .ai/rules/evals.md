---
paths:
  - 'tests/Evals/**'
---

# Evals

## Evals wrap live services locally
Pest Evals wrap ChatService, TicketIntakeService, and SuggestedReplyService against KnowledgeSeeder. Do not prompt SupportChatStreamAgent without retrieval. Skip unless --evals. Keep them out of composer test and GitHub Actions. Do not fake embeddings or agents. OPENAI_SMOKE stays until triage evals cover it.
