<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DemoEnvironment extends Component
{
    public function render(): View
    {
        return view('livewire.pages.demo-environment')
            ->layout('components.layouts.public', ['title' => 'Demo environment']);
    }
}
