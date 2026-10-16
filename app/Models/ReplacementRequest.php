<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $replacement_case_id
 * @property string $proposal_id
 * @property string $reference
 * @property string $order_number
 * @property string $sku
 * @property string $item_name
 * @property int $quantity
 * @property string $reason
 */
#[Fillable([
    'replacement_case_id',
    'proposal_id',
    'reference',
    'order_number',
    'sku',
    'item_name',
    'quantity',
    'reason',
])]
class ReplacementRequest extends Model
{
    use HasUuids;

    /** @return BelongsTo<ReplacementCase, $this> */
    public function replacementCase(): BelongsTo
    {
        return $this->belongsTo(ReplacementCase::class);
    }

    /** @return BelongsTo<ReplacementProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ReplacementProposal::class);
    }
}
