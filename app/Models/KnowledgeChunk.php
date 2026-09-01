<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<float>|null $embedding
 * @property string $body
 * @property string|null $heading
 * @property int $token_count
 */
#[Fillable(['knowledge_article_id', 'heading', 'body', 'token_count', 'embedding'])]
class KnowledgeChunk extends Model
{
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'token_count' => 'integer',
        ];
    }

    /** @return BelongsTo<KnowledgeArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }
}
