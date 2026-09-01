<?php

namespace App\Livewire\Pages;

use App\Enums\TicketStatus as TicketStatusEnum;
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
        if (in_array($this->ticket->status, [TicketStatusEnum::Submitted, TicketStatusEnum::Triaging], true)) {
            return true;
        }

        if ($this->ticket->status !== TicketStatusEnum::AwaitingReview) {
            return false;
        }

        return ! $this->ticket->messages()
            ->where('visibility', 'public')
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
