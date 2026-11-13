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
        return view('livewire.pages.welcome')->layout('components.layouts.lucid', [
            'metaTitle' => 'Lucid | AI for customer support and business automation',
            'description' => 'Explore working AI demos for customer support, document answers, invoice review, and approved order actions. Custom software. Practical AI.',
            'canonical' => route('home'),
        ]);
    }
}
