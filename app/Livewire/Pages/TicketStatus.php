<?php

namespace App\Livewire\Pages;

use App\Enums\MessageAuthorType;
use App\Enums\TicketStatus as TicketStatusValue;
use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TicketStatus extends Component
{
    use HeartbeatsDemoSession;

    public Ticket $ticket;

    public bool $embedded = false;

    #[Locked]
    public ?bool $lastHasSentReply = null;

    public function mount(string $publicToken): void
    {
        $this->ticket = Ticket::query()
            ->where('public_token', $publicToken)
            ->firstOrFail();
    }

    public function shouldPoll(): bool
    {
        if ($this->ticket->status === TicketStatusValue::AwaitingStaff) {
            return true;
        }

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

        $hasSentReply = $messages->contains('author_type', MessageAuthorType::Agent);
        if ($this->embedded && $this->lastHasSentReply !== null && $this->lastHasSentReply !== $hasSentReply) {
            $this->dispatch('ticket-walkthrough-changed');
        }
        $this->lastHasSentReply = $hasSentReply;

        return view('livewire.pages.ticket-status', [
            'messages' => $messages,
        ])->layout('components.layouts.lucid', ['title' => $this->ticket->reference]);
    }
}
