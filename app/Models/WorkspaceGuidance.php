<?php

namespace App\Models;

use App\Enums\WorkspaceGuidanceCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $workspace_id
 * @property WorkspaceGuidanceCategory $category
 * @property string $body
 * @property int $position
 */
#[Fillable(['workspace_id', 'category', 'body', 'position'])]
class WorkspaceGuidance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => WorkspaceGuidanceCategory::class,
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
