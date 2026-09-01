<?php

namespace App\Livewire\Pages;

use App\Enums\TicketCategory;
use App\Models\KnowledgeArticle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class KnowledgeIndex extends Component
{
    public string $category = 'all';

    public function filterCategory(string $category): void
    {
        $this->category = $category === 'all' || TicketCategory::tryFrom($category) !== null
            ? $category
            : 'all';
    }

    public function askAssistant(): void
    {
        $this->dispatch('demo-open-chat');
    }

    public function render(): View
    {
        $articles = KnowledgeArticle::query()
            ->where('is_published', true)
            ->where('is_seeded', true)
            ->orderBy('title')
            ->get();

        if ($this->category !== 'all') {
            $articles = $articles->filter(
                fn (KnowledgeArticle $article): bool => $article->category->value === $this->category,
            );
        }

        /** @var Collection<string, Collection<int, KnowledgeArticle>> $grouped */
        $grouped = $articles->groupBy(fn (KnowledgeArticle $article): string => $article->category->value);

        return view('livewire.pages.knowledge-index', [
            'grouped' => $grouped,
            'categories' => TicketCategory::cases(),
        ])->layout('components.layouts.public', ['title' => 'Policies']);
    }
}
