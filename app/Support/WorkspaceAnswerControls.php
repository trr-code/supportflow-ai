<?php

namespace App\Support;

use App\Enums\WorkspaceAnswerLength;
use App\Enums\WorkspaceGuidanceCategory;
use App\Enums\WorkspaceTone;
use App\Models\Workspace;
use App\Models\WorkspaceGuidance;

final class WorkspaceAnswerControls
{
    /**
     * @param  list<array{category: WorkspaceGuidanceCategory, body: string}>  $guidances
     */
    public function __construct(
        public WorkspaceTone $tone,
        public WorkspaceAnswerLength $length,
        public array $guidances,
    ) {}

    public static function fromWorkspace(Workspace $workspace): self
    {
        $notes = array_values($workspace->guidances()
            ->orderBy('position')
            ->get()
            ->map(fn (WorkspaceGuidance $note): array => [
                'category' => $note->category,
                'body' => $note->body,
            ])
            ->all());

        return new self($workspace->tone, $workspace->answer_length, $notes);
    }

    /**
     * @param  list<array{category: string, body: string}>|null  $guidances
     */
    public static function fromDraft(Workspace $workspace, ?string $tone, ?string $length, ?array $guidances): self
    {
        $saved = self::fromWorkspace($workspace);
        $resolvedTone = WorkspaceTone::tryFrom((string) $tone) ?? $saved->tone;
        $resolvedLength = WorkspaceAnswerLength::tryFrom((string) $length) ?? $saved->length;

        if ($guidances === null) {
            return new self($resolvedTone, $resolvedLength, $saved->guidances);
        }

        $notes = [];

        foreach (array_slice($guidances, 0, 5) as $note) {
            $body = trim((string) ($note['body'] ?? ''));
            $category = WorkspaceGuidanceCategory::tryFrom((string) ($note['category'] ?? ''));

            if ($body === '' || $category === null || mb_strlen($body) > 800) {
                continue;
            }

            $notes[] = [
                'category' => $category,
                'body' => $body,
            ];
        }

        return new self($resolvedTone, $resolvedLength, $notes);
    }
}
