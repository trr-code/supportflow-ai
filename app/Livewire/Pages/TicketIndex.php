<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Models\Ticket;
use App\Services\DemoPruneService;
use App\Services\DemoScenarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class TicketIndex extends Component
{
    use HeartbeatsDemoSession;
    use WithPagination;

    public string $filter = 'all';

    public ?string $notice = null;

    public bool $showScenarios = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Ticket::class);
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function launch(string $key, DemoScenarioService $scenarios): mixed
    {
        $this->authorize('viewAny', Ticket::class);

        $ticket = $scenarios->launch($key);

        return $this->redirectRoute('agent.tickets.show', $ticket, navigate: true);
    }

    public function pruneStale(DemoPruneService $prune): void
    {
        $this->authorize('prune-stale-demo');

        $key = 'demo.prune-stale|'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = max(1, RateLimiter::availableIn($key));
            $unit = $seconds === 1 ? 'second' : 'seconds';
            $this->notice = "Reset limit reached. Try again in {$seconds} {$unit}.";
        } else {
            RateLimiter::hit($key, 60);
            $result = $prune->pruneStale();
            $this->notice = "Removed {$result['tickets']} stale visitor tickets and {$result['sessions']} idle sessions. Seeded showcase tickets were kept.";
        }

        $this->dispatch('modal-close', name: 'confirm-demo-reset');
    }

    public function render(DemoScenarioService $scenarios): View
    {
        $query = Ticket::query()->latest();

        $query = match ($this->filter) {
            'awaiting' => $query->where('status', 'awaiting_review'),
            'urgent' => $query->where('priority', 'urgent'),
            'escalated' => $query->where('status', 'escalated'),
            'failed' => $query->where('status', 'ai_failed'),
            'live' => $query->where('source', 'visitor_demo')->where('is_seeded', false),
            default => $query,
        };

        return view('livewire.pages.ticket-index', [
            'tickets' => $query->paginate(15),
            'scenarios' => $scenarios->catalog(),
        ])->layout('layouts.app', ['title' => 'Ticket queue']);
    }
}
