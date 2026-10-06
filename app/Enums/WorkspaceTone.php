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
            self::Professional => 'Voice: courteous, with no emoji and no exclamation marks. Include please or thank you once, and do not skip that courtesy.',
            self::Friendly => 'Voice: the first sentence must be noticeably warm, the way one person reassures another, and it must use a contraction such as it\'s, you\'re, or we\'ll. An emoji is optional, not required. Do not start with Hi.',
            self::MatterOfFact => 'Voice: short sentences only, with no courtesy opening, no please or thank you, and no emoji.',
        };
    }
}
