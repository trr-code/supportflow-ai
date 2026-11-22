<?php

namespace App\Enums;

enum WorkspaceTone: string
{
    case Professional = 'professional';
    case Friendly = 'friendly';
    case MatterOfFact = 'matter-of-fact';

    public function label(): string
    {
        return match ($this) {
            self::Professional => 'Professional',
            self::Friendly => 'Friendly',
            self::MatterOfFact => 'Matter-of-fact',
        };
    }

    public function voice(): string
    {
        return match ($this) {
            self::Professional => 'Voice: courteous through clear, direct wording. Use polished business prose with neutral labels and complete formal sentences, no emoji and no exclamation marks. Avoid conversational asides and contractions.',
            self::Friendly => 'Voice: warm, natural and approachable business conversation. Address the reader as you and your when explaining how a rule applies, use natural contractions and plain everyday verbs instead of formal wording such as qualifies, occurs or authorization is required. Keep all exact amounts, conditions and exceptions, without reassuring the reader that they qualify or have approval. Do not force an opening greeting, emoji or filler.',
            self::MatterOfFact => 'Voice: short sentences only, with no courtesy opening, no please or thank you, and no emoji.',
        };
    }
}
