<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $last_activity_at
 * @property string|null $ip_hash
 */
#[Fillable(['id', 'ip_hash', 'last_activity_at'])]
class DemoSession extends Model
{
    #[\Override]
    public $incrementing = false;

    #[\Override]
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'demo_session_id');
    }

    public function isStale(): bool
    {
        $minutes = (int) config('supportflow.demo.stale_minutes');

        return $this->last_activity_at->lt(now()->subMinutes($minutes));
    }
}
