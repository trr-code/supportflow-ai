<?php

namespace App\Enums;

enum KnowledgeMatchLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case None = 'none';

    public static function fromSimilarity(?float $similarity): self
    {
        if ($similarity === null) {
            return self::None;
        }

        $high = (float) config('supportflow.retrieval.high');
        $medium = (float) config('supportflow.retrieval.medium');
        $min = (float) config('supportflow.retrieval.min_similarity');

        return match (true) {
            $similarity >= $high => self::High,
            $similarity >= $medium => self::Medium,
            $similarity >= $min => self::Low,
            default => self::None,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::High => 'High match',
            self::Medium => 'Medium match',
            self::Low => 'Low match',
            self::None => 'No sufficient match',
        };
    }
}
