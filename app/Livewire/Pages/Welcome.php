<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\HeartbeatsDemoSession;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Welcome extends Component
{
    use HeartbeatsDemoSession;

    public function render(): View
    {
        return view('livewire.pages.welcome')->layout('components.layouts.lucid');
    }
}
