<?php

namespace App\Models;

use App\Enums\ClassificationConfidenceLevel;
use App\Enums\KnowledgeMatchLevel;
use App\Enums\TicketCategory;
use App\Enums\TicketDepartment;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property TicketStatus $status
 * @property TicketCategory|null $category
 * @property TicketPriority|null $priority
 * @property TicketSentiment|null $sentiment
 * @property TicketDepartment|null $department
 * @property TicketCategory|null $ai_category
 * @property TicketPriority|null $ai_priority
 * @property TicketSentiment|null $ai_sentiment
 * @property TicketDepartment|null $ai_department
 * @property TicketSource $source
 * @property list<string>|null $decision_factors
 * @property float|null $classification_confidence
 * @property float|null $retrieval_similarity
 * @property bool $injection_suspected
 * @property bool $needs_human
 * @property bool $is_seeded
 * @property string $reference
 * @property string $public_token
 * @property string $customer_name
 * @property string $customer_email
 * @property string $subject
 * @property string $description
 * @property string|null $product
 * @property string|null $ai_summary
 * @property string|null $demo_session_id
 * @property string|null $scenario_key
 * @property Carbon|null $escalated_at
 */
#[Fillable([
    'reference',
    'public_token',
    'customer_name',
    'customer_email',
    'subject',
    'description',
    'product',
    'status',
    'category',
    'priority',
    'sentiment',
    'department',
    'ai_category',
    'ai_priority',
    'ai_sentiment',
    'ai_department',
    'ai_summary',
    'decision_factors',
    'classification_confidence',
    'retrieval_similarity',
    'injection_suspected',
    'needs_human',
    'is_seeded',
    'source',
    'demo_session_id',
    'scenario_key',
    'escalated_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'sentiment' => TicketSentiment::class,
            'department' => TicketDepartment::class,
            'ai_category' => TicketCategory::class,
            'ai_priority' => TicketPriority::class,
            'ai_sentiment' => TicketSentiment::class,
            'ai_department' => TicketDepartment::class,
            'source' => TicketSource::class,
            'decision_factors' => 'array',
            'classification_confidence' => 'float',
            'retrieval_similarity' => 'float',
            'injection_suspected' => 'boolean',
            'needs_human' => 'boolean',
            'is_seeded' => 'boolean',
            'escalated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->public_token ??= bin2hex(random_bytes(16));
            $ticket->reference ??= 'SF-'.strtoupper(Str::random(5));
        });
    }

    /** @return BelongsTo<DemoSession, $this> */
    public function demoSession(): BelongsTo
    {
        return $this->belongsTo(DemoSession::class);
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /** @return HasMany<TicketEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    /** @return HasMany<SuggestedReply, $this> */
    public function suggestedReplies(): HasMany
    {
        return $this->hasMany(SuggestedReply::class);
    }

    /** @return HasOne<SuggestedReply, $this> */
    public function latestSuggestedReply(): HasOne
    {
        return $this->hasOne(SuggestedReply::class)->latestOfMany();
    }

    /** @return HasMany<AiRun, $this> */
    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }

    /** @return HasMany<TicketMessage, $this> */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('visibility', 'public')->whereNotNull('approved_at');
    }

    public function knowledgeMatchLevel(): KnowledgeMatchLevel
    {
        return KnowledgeMatchLevel::fromSimilarity($this->retrieval_similarity);
    }

    public function classificationConfidenceLevel(): ClassificationConfidenceLevel
    {
        return ClassificationConfidenceLevel::fromScore($this->classification_confidence);
    }

    public function routeKeyName(): string
    {
        return 'id';
    }
}
