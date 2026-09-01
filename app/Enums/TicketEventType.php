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
}
