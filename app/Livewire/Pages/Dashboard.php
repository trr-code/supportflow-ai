<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Models\Ticket;
use App\Services\DashboardMetrics;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    use HeartbeatsDemoSession;

    public function mount(): void
    {
        $this->authorize('viewAny', Ticket::class);
    }

    public function render(DashboardMetrics $metrics): View
    {
        return view('livewire.pages.dashboard', [
            'metrics' => $metrics->snapshot(),
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
