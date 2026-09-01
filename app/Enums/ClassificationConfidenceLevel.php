<?php

namespace App\Enums;

enum ClassificationConfidenceLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Unknown = 'unknown';

    public static function fromScore(?float $score): self
    {
        if ($score === null) {
            return self::Unknown;
        }

        return match (true) {
            $score >= 0.75 => self::High,
            $score >= 0.5 => self::Medium,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
            self::Unknown => 'Unknown',
        };
    }
}
