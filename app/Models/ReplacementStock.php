<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $replacement_case_id
 * @property string $sku
 * @property int $on_hand
 */
#[Fillable(['replacement_case_id', 'sku', 'on_hand'])]
class ReplacementStock extends Model
{
    #[\Override]
    protected $table = 'replacement_stock';

    /** @return BelongsTo<ReplacementCase, $this> */
    public function replacementCase(): BelongsTo
    {
        return $this->belongsTo(ReplacementCase::class);
    }
}
