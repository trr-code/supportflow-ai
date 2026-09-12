<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DemoWorkflow extends Component
{
    public function render(): View
    {
        return view('livewire.pages.demo-workflow')
            ->layout('components.layouts.public', ['title' => 'Complete support workflow']);
    }
}
