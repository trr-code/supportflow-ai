<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Submitted = 'submitted';
    case Triaging = 'triaging';
    case AwaitingReview = 'awaiting_review';
    case WaitingOnCustomer = 'waiting_on_customer';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case AiFailed = 'ai_failed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Triaging => 'AI reviewing',
            self::AwaitingReview => 'Awaiting review',
            self::WaitingOnCustomer => 'Waiting on customer',
            self::Escalated => 'Escalated to human',
            self::Resolved => 'Resolved',
            self::AiFailed => 'AI unavailable',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }
}
