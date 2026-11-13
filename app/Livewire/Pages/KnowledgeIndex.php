<?php

namespace App\Livewire\Pages;

use App\Enums\TicketCategory;
use App\Models\KnowledgeArticle;
use App\Support\PolicyNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class KnowledgeIndex extends Component
{
    public string $category = 'all';

    /** @var array{from?: string, ticket?: int, message?: int, citation?: int} */
    #[Locked]
    public array $origin = [];

    public function mount(): void
    {
        $this->origin = PolicyNavigation::fromQuery();
        $topic = request()->query('topic');
        $this->filterCategory(is_string($topic) ? $topic : 'all');
    }

    public function filterCategory(string $category): void
    {
        $this->category = $category === 'all' || TicketCategory::tryFrom($category) !== null
            ? $category
            : 'all';
    }

    public function render(): View
    {
        $query = KnowledgeArticle::query()
            ->whereNull('workspace_id')
            ->where('is_published', true)
            ->where('is_seeded', true);

        if ($this->category !== 'all') {
            $query->where('category', $this->category);
        }

        $articles = $query->orderBy('title')->get();

        /** @var Collection<string, Collection<int, KnowledgeArticle>> $grouped */
        $grouped = $articles->groupBy(fn (KnowledgeArticle $article): string => $article->category->value);

        return view('livewire.pages.knowledge-index', [
            'grouped' => $grouped,
            'categories' => TicketCategory::cases(),
            'returnUrl' => PolicyNavigation::returnUrl($this->origin),
            'returnLabel' => PolicyNavigation::returnLabel($this->origin),
        ])->layout('components.layouts.lucid', [
            'title' => 'Policies',
            'description' => 'Browse the published fictional Harbor store policies used in Lucid customer support.',
            'canonical' => route('knowledge.index'),
            'policyAgentContext' => ($this->origin['from'] ?? null) === 'ticket',
        ]);
    }
}
