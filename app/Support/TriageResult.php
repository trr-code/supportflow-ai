<?php

namespace App\Support;

use App\Enums\TicketCategory;
use App\Enums\TicketDepartment;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TriageResult
{
    /**
     * @param  list<string>  $decisionFactors
     */
    public function __construct(
        public TicketCategory $category,
        public TicketPriority $priority,
        public TicketSentiment $sentiment,
        public TicketDepartment $department,
        public string $summary,
        public float $classificationConfidence,
        public array $decisionFactors,
        public bool $injectionSuspected,
        public bool $needsHuman,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $validator = validator($data, [
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'sentiment' => ['required', Rule::enum(TicketSentiment::class)],
            'department' => ['required', Rule::enum(TicketDepartment::class)],
            'summary' => ['required', 'string', 'max:500'],
            'classification_confidence' => ['required', 'numeric', 'between:0,1'],
            'decision_factors' => ['required', 'array', 'min:1', 'max:6'],
            'decision_factors.*' => ['string', 'max:200'],
            'injection_suspected' => ['required', 'boolean'],
            'needs_human' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $valid = $validator->validated();

        return new self(
            category: TicketCategory::from($valid['category']),
            priority: TicketPriority::from($valid['priority']),
            sentiment: TicketSentiment::from($valid['sentiment']),
            department: TicketDepartment::from($valid['department']),
            summary: $valid['summary'],
            classificationConfidence: (float) $valid['classification_confidence'],
            decisionFactors: array_values($valid['decision_factors']),
            injectionSuspected: (bool) $valid['injection_suspected'],
            needsHuman: (bool) $valid['needs_human'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category->value,
            'priority' => $this->priority->value,
            'sentiment' => $this->sentiment->value,
            'department' => $this->department->value,
            'summary' => $this->summary,
            'classification_confidence' => $this->classificationConfidence,
            'decision_factors' => $this->decisionFactors,
            'injection_suspected' => $this->injectionSuspected,
            'needs_human' => $this->needsHuman,
        ];
    }
}
