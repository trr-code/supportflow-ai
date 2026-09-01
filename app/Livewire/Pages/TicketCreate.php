<?php

namespace App\Livewire\Pages;

use App\Exceptions\DemoCapReachedException;
use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Services\DemoScenarioService;
use App\Services\TicketService;
use App\Support\DemoGuide;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Session as LivewireSession;
use Livewire\Attributes\Validate;
use Livewire\Component;

class TicketCreate extends Component
{
    use HeartbeatsDemoSession;

    #[LivewireSession(key: 'supportflow.ticket.customer_name')]
    #[Validate('required|string|max:80')]
    public string $customer_name = '';

    #[LivewireSession(key: 'supportflow.ticket.customer_email')]
    #[Validate('required|email:rfc|max:120')]
    public string $customer_email = '';

    #[LivewireSession(key: 'supportflow.ticket.subject')]
    #[Validate('required|string|max:160')]
    public string $subject = '';

    #[LivewireSession(key: 'supportflow.ticket.description')]
    #[Validate('required|string|min:20|max:4000')]
    public string $description = '';

    #[LivewireSession(key: 'supportflow.ticket.product')]
    #[Validate('nullable|string|max:80')]
    public string $product = '';

    public ?string $capMessage = null;

    public bool $draftRestored = false;

    public function mount(): void
    {
        $hadDraft = $this->hasDraftContent();

        if ($prefill = request()->string('subject')->toString()) {
            $this->subject = $prefill;
            $this->draftRestored = false;

            return;
        }

        $this->draftRestored = $hadDraft;
    }

    public function fillSample(DemoScenarioService $scenarios): void
    {
        $sample = $scenarios->sample(DemoGuide::SAMPLE_TICKET_KEY);
        $this->customer_name = $sample['customer_name'];
        $this->customer_email = $sample['customer_email'];
        $this->subject = $sample['subject'];
        $this->description = $sample['description'];
        $this->product = $sample['product'];
        $this->resetErrorBag();
    }

    public function submit(TicketService $tickets): mixed
    {
        $this->validate();

        $key = 'tickets|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('subject', 'Too many tickets from this network. Please wait a minute.');

            return null;
        }

        RateLimiter::hit($key, 60);

        try {
            $ticket = $tickets->createVisitorTicket([
                'customer_name' => $this->customer_name,
                'customer_email' => $this->customer_email,
                'subject' => $this->subject,
                'description' => $this->description,
                'product' => $this->product !== '' ? $this->product : null,
            ], $this->demoSession());
        } catch (DemoCapReachedException $exception) {
            $this->capMessage = $exception->getMessage();

            return null;
        }

        $this->reset([
            'customer_name',
            'customer_email',
            'subject',
            'description',
            'product',
            'draftRestored',
            'capMessage',
        ]);
        $this->forgetTicketDraft();

        return $this->redirectRoute('tickets.status', ['publicToken' => $ticket->public_token], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.pages.ticket-create')
            ->layout('components.layouts.public', ['title' => 'New ticket']);
    }

    protected function hasDraftContent(): bool
    {
        return collect([
            $this->customer_name,
            $this->customer_email,
            $this->subject,
            $this->product,
            $this->description,
        ])->contains(fn (string $value): bool => trim($value) !== '');
    }

    protected function forgetTicketDraft(): void
    {
        Session::forget([
            'supportflow.ticket.customer_name',
            'supportflow.ticket.customer_email',
            'supportflow.ticket.subject',
            'supportflow.ticket.product',
            'supportflow.ticket.description',
        ]);
    }
}
