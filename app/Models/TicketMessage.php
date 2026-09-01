<?php

namespace App\Models;

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'visibility', 'author_type', 'user_id', 'body', 'approved_at'])]
class TicketMessage extends Model
{
    protected function casts(): array
    {
        return [
            'visibility' => MessageVisibility::class,
            'author_type' => MessageAuthorType::class,
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
