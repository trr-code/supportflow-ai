---
paths:
  - app/Ai/Agents/SuggestedReplyAgent.php
  - app/Ai/Agents/SupportChatStreamAgent.php
---

# Agents

## Do not invent nearby store relevance
When store pickup locations are in the passages, list those documented locations as options and say pickup requires inventory confirmation. Do not imply a store is nearby, convenient, or on the customer's route unless the passages state that relationship.

## Rewrite previous drafts instead of copying them
When a previous draft is in the prompt, rewrite with different wording or paragraph structure. Keep every grounded fact, cite the same supporting passages, and do not add information that is not in the passages. Do not return an identical body.

## Chat history uses Conversational, not RemembersConversations
Use Conversational + messages() from chat_messages for the anonymous DemoSession thread. Do not use RemembersConversations, forUser(), or continue(). Omit gated injection turns and their refusal assistants from messages(); they stay in the UI store. Prior turns resolve follow-ups; facts and CITES come only from the current passages.
