<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $role
 * @property string $body
 * @property list<int>|null $cited_chunk_ids
 */
#[Fillable(['chat_conversation_id', 'role', 'body', 'cited_chunk_ids'])]
class ChatMessage extends Model
{
    protected function casts(): array
    {
        return [
            'cited_chunk_ids' => 'array',
        ];
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }
}
