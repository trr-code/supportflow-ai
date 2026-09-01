<?php

namespace App\Models;

use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AiRunFeature $feature
 * @property AiRunStatus $status
 * @property array<string, mixed>|null $payload
 * @property list<int>|null $retrieved_chunk_ids
 * @property float|null $estimated_cost
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $model
 * @property string|null $error
 */
#[Fillable([
    'feature',
    'ticket_id',
    'provider',
    'model',
    'status',
    'started_at',
    'completed_at',
    'input_tokens',
    'output_tokens',
    'total_tokens',
    'estimated_cost',
    'error',
    'retrieved_chunk_ids',
    'payload',
    'request_hash',
])]
class AiRun extends Model
{
    protected function casts(): array
    {
        return [
            'feature' => AiRunFeature::class,
            'status' => AiRunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'retrieved_chunk_ids' => 'array',
            'payload' => 'array',
            'estimated_cost' => 'float',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
