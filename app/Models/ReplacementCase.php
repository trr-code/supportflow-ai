<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $demo_session_id
 * @property string|null $conversation_id
 * @property int $generation
 * @property int $visitor_messages
 * @property string|null $run_state
 */
#[Fillable(['demo_session_id', 'conversation_id', 'generation', 'visitor_messages', 'run_state'])]
class ReplacementCase extends Model
{
    use HasUuids;

    #[\Override]
    protected $attributes = [
        'generation' => 1,
        'visitor_messages' => 0,
    ];

    /** @return BelongsTo<DemoSession, $this> */
    public function demoSession(): BelongsTo
    {
        return $this->belongsTo(DemoSession::class);
    }

    /** @return HasMany<ReplacementStock, $this> */
    public function stock(): HasMany
    {
        return $this->hasMany(ReplacementStock::class);
    }

    /** @return HasMany<ReplacementProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(ReplacementProposal::class);
    }

    /** @return HasMany<ReplacementRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(ReplacementRequest::class);
    }

    /** @return HasMany<ReplacementMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ReplacementMessage::class);
    }

    /** @return HasMany<ReplacementActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ReplacementActivity::class);
    }

    /** @return HasOne<ReplacementProposal, $this> */
    public function pendingProposal(): HasOne
    {
        return $this->hasOne(ReplacementProposal::class)->latestOfMany();
    }

    public function isBusy(): bool
    {
        return in_array($this->run_state, ['queued', 'running'], true);
    }
}
