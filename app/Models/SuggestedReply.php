<?php

namespace App\Models;

use App\Enums\SuggestedReplyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property SuggestedReplyStatus $status
 * @property string $body
 * @property bool $grounded
 * @property list<int>|null $cited_chunk_ids
 * @property string|null $refusal_reason
 */
#[Fillable(['ticket_id', 'body', 'grounded', 'status', 'cited_chunk_ids', 'refusal_reason', 'regenerated_from_id'])]
class SuggestedReply extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SuggestedReplyStatus::class,
            'grounded' => 'boolean',
            'cited_chunk_ids' => 'array',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return Collection<int, KnowledgeChunk>
     */
    public function citedChunks(): Collection
    {
        $ids = $this->cited_chunk_ids ?? [];

        if ($ids === []) {
            return collect();
        }

        return KnowledgeChunk::query()
            ->with('article')
            ->whereIn('id', $ids)
            ->get();
    }
}
