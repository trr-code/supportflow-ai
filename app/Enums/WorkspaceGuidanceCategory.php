<?php

namespace App\Enums;

enum WorkspaceGuidanceCategory: string
{
    case Wording = 'wording';
    case Clarification = 'clarification';
    case Handoff = 'handoff';

    public function label(): string
    {
        return match ($this) {
            self::Wording => 'Wording',
            self::Clarification => 'Clarification',
            self::Handoff => 'Handoff',
        };
    }
}
