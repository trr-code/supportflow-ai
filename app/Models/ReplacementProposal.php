<?php

namespace App\Models;

use App\Enums\ReplacementProposalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $replacement_case_id
 * @property int $generation
 * @property string $tool_call_id
 * @property string $argument_hash
 * @property ReplacementProposalStatus $status
 * @property string $order_number
 * @property string $sku
 * @property string $item_name
 * @property int $quantity
 * @property string $reason
 * @property string $action
 * @property array<string, mixed> $snapshot
 * @property Carbon $expires_at
 */
#[Fillable([
    'replacement_case_id',
    'generation',
    'tool_call_id',
    'argument_hash',
    'status',
    'order_number',
    'sku',
    'item_name',
    'quantity',
    'reason',
    'action',
    'snapshot',
    'expires_at',
])]
class ReplacementProposal extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReplacementProposalStatus::class,
            'snapshot' => 'array',
            'expires_at' => 'datetime',
            'generation' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<ReplacementCase, $this> */
    public function replacementCase(): BelongsTo
    {
        return $this->belongsTo(ReplacementCase::class);
    }

    /** @return HasOne<ReplacementRequest, $this> */
    public function request(): HasOne
    {
        return $this->hasOne(ReplacementRequest::class, 'proposal_id');
    }

    public function isOpen(): bool
    {
        return $this->status === ReplacementProposalStatus::Pending
            && $this->expires_at->isFuture();
    }
}
