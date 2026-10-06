<?php

namespace Database\Factories;

use App\Enums\WorkspaceAnswerLength;
use App\Enums\WorkspaceTone;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code_hash' => hash('sha256', fake()->unique()->sha1()),
            'expires_at' => now()->addDays(7),
            'purged_at' => null,
            'tone' => WorkspaceTone::Professional,
            'answer_length' => WorkspaceAnswerLength::Standard,
        ];
    }
}
