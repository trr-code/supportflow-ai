<?php

namespace App\Models;

use App\Enums\TicketCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property TicketCategory $category
 * @property string $title
 * @property string $slug
 * @property string $body
 * @property bool $is_published
 * @property bool $is_seeded
 */
#[Fillable(['title', 'slug', 'category', 'body', 'is_published', 'is_seeded'])]
class KnowledgeArticle extends Model
{
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'is_published' => 'boolean',
            'is_seeded' => 'boolean',
        ];
    }

    /** @return HasMany<KnowledgeChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function excerpt(): string
    {
        $withoutHeadings = preg_replace('/^#+\s+/m', '', $this->body) ?? $this->body;
        $paragraphs = preg_split('/\n\s*\n/', trim($withoutHeadings)) ?: [];
        $first = trim((string) ($paragraphs[0] ?? ''));
        $first = preg_replace('/\s+/', ' ', $first) ?? $first;

        return Str::limit($first, 180);
    }

    /**
     * @return list<string>
     */
    public function headingNames(): array
    {
        preg_match_all('/^##\s+(.+)$/m', $this->body, $matches);

        return array_values(array_filter(array_map(
            trim(...),
            $matches[1],
        )));
    }

    public function bodyHtml(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
