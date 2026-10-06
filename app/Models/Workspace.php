<?php

namespace App\Models;

use App\Enums\WorkspaceAnswerLength;
use App\Enums\WorkspaceTone;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $purged_at
 * @property WorkspaceTone $tone
 * @property WorkspaceAnswerLength $answer_length
 */
#[Fillable(['code_hash', 'expires_at', 'purged_at', 'tone', 'answer_length'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'purged_at' => 'datetime',
            'tone' => WorkspaceTone::class,
            'answer_length' => WorkspaceAnswerLength::class,
        ];
    }

    public function isOpen(): bool
    {
        return $this->purged_at === null && $this->expires_at->isFuture();
    }

    /** @return HasMany<WorkspaceDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(WorkspaceDocument::class);
    }

    /** @return HasMany<WorkspaceGuidance, $this> */
    public function guidances(): HasMany
    {
        return $this->hasMany(WorkspaceGuidance::class)->orderBy('position');
    }

    /** @return HasMany<WorkspaceBrowserToken, $this> */
    public function browserTokens(): HasMany
    {
        return $this->hasMany(WorkspaceBrowserToken::class);
    }

    /** @return HasMany<ChatConversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }
}
