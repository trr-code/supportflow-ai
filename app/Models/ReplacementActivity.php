<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $replacement_case_id
 * @property int $generation
 * @property string $summary
 */
#[Fillable(['replacement_case_id', 'generation', 'summary'])]
class ReplacementActivity extends Model
{
    /** @return BelongsTo<ReplacementCase, $this> */
    public function replacementCase(): BelongsTo
    {
        return $this->belongsTo(ReplacementCase::class);
    }
}
