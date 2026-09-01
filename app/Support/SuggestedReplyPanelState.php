<?php

namespace App\Support;

use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\SuggestedReplyPanelKind;
use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\TicketEvent;

class SuggestedReplyPanelState
{
    public function __construct(
        public SuggestedReplyPanelKind $kind,
        public string $message,
    ) {}

    public static function for(
        Ticket $ticket,
        ?SuggestedReply $pending,
        ?SuggestedReply $latest,
        bool $aiRunning = false,
    ): self {
        if ($pending !== null) {
            return new self(
                SuggestedReplyPanelKind::AwaitingReview,
                SuggestedReplyPanelKind::AwaitingReview->message(),
            );
        }

        if ($aiRunning || $ticket->status === TicketStatus::Triaging) {
            return self::of(SuggestedReplyPanelKind::Regenerating);
        }

        if ($ticket->injection_suspected) {
            return self::of(SuggestedReplyPanelKind::PromptInjection);
        }

        if ($ticket->status === TicketStatus::AiFailed) {
            return self::of(SuggestedReplyPanelKind::AiFailed);
        }

        if ($latest?->status === SuggestedReplyStatus::Rejected) {
            return self::of(SuggestedReplyPanelKind::Rejected);
        }

        $latestEvent = $ticket->relationLoaded('events')
            ? $ticket->events->sortByDesc('id')->first()
            : $ticket->events()->latest('id')->first();

        if ($latestEvent instanceof TicketEvent) {
            if ($latestEvent->type === TicketEventType::SuggestionRejected) {
                return self::of(SuggestedReplyPanelKind::Rejected);
            }

            if ($latestEvent->type === TicketEventType::Escalated) {
                $reason = is_array($latestEvent->payload) ? (string) ($latestEvent->payload['reason'] ?? '') : '';

                if (in_array($reason, ['insufficient_knowledge', 'unsupported'], true)) {
                    return self::of(SuggestedReplyPanelKind::InsufficientKnowledge);
                }

                if ($reason === 'injection_suspected') {
                    return self::of(SuggestedReplyPanelKind::PromptInjection);
                }
            }
        }

        if ($ticket->status === TicketStatus::Escalated || $ticket->needs_human) {
            return self::of(SuggestedReplyPanelKind::Escalated);
        }

        return self::of(SuggestedReplyPanelKind::NoDraftYet);
    }

    public static function running(Ticket $ticket): bool
    {
        return $ticket->aiRuns()
            ->where('status', AiRunStatus::Running)
            ->whereIn('feature', [AiRunFeature::Triage, AiRunFeature::SuggestedReply])
            ->exists();
    }

    protected static function of(SuggestedReplyPanelKind $kind): self
    {
        return new self($kind, $kind->message());
    }
}
