<?php

namespace App\Livewire\Pages;

use App\Support\DemoGuide;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Welcome extends Component
{
    public function fillChat(string $key): void
    {
        if (DemoGuide::prompt($key) === null) {
            return;
        }

        $this->dispatch('demo-fill-chat', key: $key);
    }

    public function render(): View
    {
        return view('livewire.pages.welcome', [
            'primaryPrompts' => DemoGuide::primaryChatPrompts(),
            'advancedPrompts' => DemoGuide::advancedChatPrompts(),
        ])->layout('components.layouts.public');
    }
}
