<?php

namespace App\Enums;

enum SuggestedReplyPanelKind: string
{
    case AwaitingReview = 'awaiting_review';
    case Rejected = 'rejected';
    case InsufficientKnowledge = 'insufficient_knowledge';
    case PromptInjection = 'prompt_injection';
    case AiFailed = 'ai_failed';
    case Regenerating = 'regenerating';
    case Escalated = 'escalated';
    case NoDraftYet = 'no_draft_yet';

    public function message(): string
    {
        return match ($this) {
            self::AwaitingReview => 'The customer cannot see this until you approve and send.',
            self::Rejected => 'You rejected this draft. Write a human reply or regenerate a new suggestion.',
            self::InsufficientKnowledge => 'Not enough knowledge—assigned to a human. Write a custom reply, or regenerate if a better match is available.',
            self::PromptInjection => 'Prompt-injection suspected. The draft was skipped. Write a human reply.',
            self::AiFailed => 'AI is unavailable. Retry intake, or write a human reply.',
            self::Regenerating => 'A grounded draft is being generated. This page will show it when it is ready.',
            self::Escalated => 'This ticket is assigned to a human. Write a custom reply below.',
            self::NoDraftYet => 'No pending draft yet. AI may still be reviewing this ticket.',
        };
    }
}
