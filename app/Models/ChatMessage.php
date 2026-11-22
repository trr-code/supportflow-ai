<?php

namespace App\Models;

use App\Support\ChatAnswerHtml;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $role
 * @property string $body
 * @property list<int>|null $cited_chunk_ids
 */
#[Fillable(['chat_conversation_id', 'role', 'body', 'cited_chunk_ids', 'is_stopped'])]
class ChatMessage extends Model
{
    public function keepRevealedPrefix(int $characters): void
    {
        $prefix = ChatAnswerHtml::revealedText($this->body, max(0, $characters));

        $this->update(['body' => $prefix !== '' ? $prefix : 'Stopped.', 'cited_chunk_ids' => [], 'is_stopped' => true]);
    }

    protected function casts(): array
    {
        return [
            'cited_chunk_ids' => 'array',
            'is_stopped' => 'boolean',
        ];
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }
}
