<?php

namespace App\Jobs;

use App\Services\ReplacementCaseService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunReplacementAgent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 70;

    public int $uniqueFor = 90;

    public function __construct(
        public string $caseId,
        public int $generation,
        public string $mode,
        public string $prompt = '',
        public ?string $toolCallId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->caseId;
    }

    public function handle(ReplacementCaseService $replacements): void
    {
        $replacements->execute($this->caseId, $this->generation, $this->mode, $this->prompt, $this->toolCallId);
    }
}
