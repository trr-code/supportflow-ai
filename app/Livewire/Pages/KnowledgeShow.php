<?php

namespace App\Livewire\Pages;

use App\Models\KnowledgeArticle;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class KnowledgeShow extends Component
{
    public KnowledgeArticle $article;

    public function mount(string $slug): void
    {
        $this->article = KnowledgeArticle::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->where('is_seeded', true)
            ->firstOrFail();
    }

    public function askAssistant(): void
    {
        $this->dispatch('demo-open-chat');
    }

    public function render(): View
    {
        return view('livewire.pages.knowledge-show')
            ->layout('components.layouts.public', ['title' => $this->article->title]);
    }
}
