<?php

namespace App\Models;

use App\Enums\WorkspaceDocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $workspace_id
 * @property string $original_name
 * @property string $storage_path
 * @property string $mime
 * @property string $extension
 * @property int $byte_size
 * @property WorkspaceDocumentStatus $status
 * @property string $index_generation
 * @property string|null $failure_reason
 * @property int|null $extracted_characters
 * @property int|null $knowledge_article_id
 */
#[Fillable([
    'workspace_id',
    'original_name',
    'storage_path',
    'mime',
    'extension',
    'byte_size',
    'status',
    'index_generation',
    'failure_reason',
    'extracted_characters',
    'knowledge_article_id',
])]
class WorkspaceDocument extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkspaceDocumentStatus::class,
            'byte_size' => 'integer',
            'extracted_characters' => 'integer',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<KnowledgeArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }
}
