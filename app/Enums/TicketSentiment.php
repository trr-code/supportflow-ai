<?php

namespace App\Enums;

enum TicketSentiment: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';
    case Angry = 'angry';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Positive',
            self::Neutral => 'Neutral',
            self::Negative => 'Negative',
            self::Angry => 'Angry',
        };
    }
}
