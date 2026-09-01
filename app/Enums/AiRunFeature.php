<?php

namespace App\Enums;

enum AiRunFeature: string
{
    case Triage = 'triage';
    case SuggestedReply = 'suggested_reply';
    case Chat = 'chat';
    case Embedding = 'embedding';
    case Transcription = 'transcription';
}
