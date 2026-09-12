<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Welcome extends Component
{
    public function openChat(): void
    {
        $this->dispatch('demo-open-chat');
    }

    public function render(): View
    {
        return view('livewire.pages.welcome')->layout('components.layouts.public');
    }
}
