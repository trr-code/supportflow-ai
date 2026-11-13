<?php

namespace App\Enums;

enum TicketEventType: string
{
    case Created = 'created';
    case TriageStarted = 'triage_started';
    case TriageCompleted = 'triage_completed';
    case TriageFailed = 'triage_failed';
    case ClassificationOverridden = 'classification_overridden';
    case RetrievalCompleted = 'retrieval_completed';
    case SuggestionGenerated = 'suggestion_generated';
    case SuggestionFailed = 'suggestion_failed';
    case SuggestionEdited = 'suggestion_edited';
    case SuggestionApproved = 'suggestion_approved';
    case SuggestionRejected = 'suggestion_rejected';
    case SuggestionRegenerated = 'suggestion_regenerated';
    case ReplySent = 'reply_sent';
    case NoteAdded = 'note_added';
    case StatusChanged = 'status_changed';
    case Escalated = 'escalated';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Ticket created',
            self::TriageStarted => 'AI assessment started',
            self::TriageCompleted => 'AI assessment completed',
            self::TriageFailed => 'AI assessment failed',
            self::ClassificationOverridden => 'Ticket details updated',
            self::RetrievalCompleted => 'Policy search completed',
            self::SuggestionGenerated => 'Suggested reply prepared',
            self::SuggestionFailed => 'Suggested reply could not be prepared',
            self::SuggestionEdited => 'Reply draft edited',
            self::SuggestionApproved => 'Suggested reply approved',
            self::SuggestionRejected => 'Suggested reply rejected',
            self::SuggestionRegenerated => 'Suggested reply regenerated',
            self::ReplySent => 'Reply sent',
            self::NoteAdded => 'Internal note added',
            self::StatusChanged => 'Ticket status changed',
            self::Escalated => 'Ticket escalated',
        };
    }
}
