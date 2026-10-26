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

## Order tools join Harbor chat only for an order turn
Policy questions stay on passages and CITES, with no order tools. A question that names a fictional order or asks for a replacement attaches LookupOrder, CheckDamagePolicy, CheckStock, and CreateReplacementRequest for that demo session. Order facts come only from those tools. A proposal still waits for confirm or cancel. Text with no citation and no tool result stays the documented refusal. Private workspace chunks stay out of this prompt.
