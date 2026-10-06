<?php

namespace App\Enums;

enum WorkspaceAnswerLength: string
{
    case Concise = 'concise';
    case Standard = 'standard';
    case Thorough = 'thorough';

    public function label(): string
    {
        return match ($this) {
            self::Concise => 'Concise',
            self::Standard => 'Standard',
            self::Thorough => 'Thorough',
        };
    }

    public function wordBudget(): int
    {
        return match ($this) {
            self::Concise => 80,
            self::Standard => 140,
            self::Thorough => 220,
        };
    }
}
