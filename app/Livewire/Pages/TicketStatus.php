<?php

namespace App\Livewire\Pages;

use App\Enums\MessageAuthorType;
use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TicketStatus extends Component
{
    use HeartbeatsDemoSession;

    public Ticket $ticket;

    public function mount(string $publicToken): void
    {
        $this->ticket = Ticket::query()
            ->where('public_token', $publicToken)
            ->firstOrFail();
    }

    public function shouldPoll(): bool
    {
        return ! $this->ticket->messages()
            ->where('visibility', 'public')
            ->where('author_type', MessageAuthorType::Agent)
            ->whereNotNull('approved_at')
            ->exists();
    }

    public function render(): View
    {
        $this->ticket->refresh();

        $messages = $this->ticket->messages()
            ->where('visibility', 'public')
            ->whereNotNull('approved_at')
            ->orderBy('id')
            ->get();

        return view('livewire.pages.ticket-status', [
            'messages' => $messages,
        ])->layout('components.layouts.public', ['title' => $this->ticket->reference]);
    }
}
