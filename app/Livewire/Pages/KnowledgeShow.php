<?php

namespace App\Livewire\Pages;

use App\Enums\TicketCategory;
use App\Models\KnowledgeArticle;
use App\Support\PolicyNavigation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class KnowledgeShow extends Component
{
    public KnowledgeArticle $article;

    /** @var array{from?: string, ticket?: int, message?: int, citation?: int} */
    #[Locked]
    public array $origin = [];

    #[Locked]
    public string $topic = 'all';

    public function mount(string $slug): void
    {
        $this->origin = PolicyNavigation::fromQuery();
        $topic = request()->query('topic');
        $this->topic = is_string($topic) && TicketCategory::tryFrom($topic) !== null ? $topic : 'all';
        $this->article = KnowledgeArticle::query()
            ->where('slug', $slug)
            ->whereNull('workspace_id')
            ->where('is_published', true)
            ->where('is_seeded', true)
            ->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.pages.knowledge-show', [
            'returnUrl' => PolicyNavigation::returnUrl($this->origin),
            'returnLabel' => PolicyNavigation::returnLabel($this->origin),
        ])
            ->layout('components.layouts.lucid', [
                'title' => $this->article->title,
                'description' => $this->article->excerpt(),
                'canonical' => route('knowledge.show', $this->article->slug),
                'policyAgentContext' => ($this->origin['from'] ?? null) === 'ticket',
            ]);
    }
}
