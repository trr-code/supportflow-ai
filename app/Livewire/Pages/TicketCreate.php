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
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session as LivewireSession;
use Livewire\Attributes\Validate;
use Livewire\Component;

class TicketCreate extends Component
{
    use HeartbeatsDemoSession;

    public const array DRAFT_SESSION_KEYS = [
        'supportflow.ticket.customer_name',
        'supportflow.ticket.return_review_order',
        'supportflow.ticket.customer_email',
        'supportflow.ticket.subject',
        'supportflow.ticket.product',
        'supportflow.ticket.description',
    ];

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

    public bool $prepareSample = false;

    public bool $startBlank = false;

    #[Locked]
    public ?string $returnReviewOrder = null;

    #[Locked]
    public int $returnReviewAfterTicketId = 0;

    public function mount(DemoScenarioService $scenarios): void
    {
        if ($this->prepareSample || request()->boolean('sample')) {
            $this->fillSample($scenarios);

            return;
        }

        if ($this->startBlank) {
            $this->reset([
                'customer_name',
                'customer_email',
                'subject',
                'description',
                'product',
                'capMessage',
            ]);
            $this->forgetTicketDraft();

            return;
        }

        if ($prefill = request()->string('subject')->toString()) {
            $this->subject = $prefill;
        }

        $this->returnReviewOrder = session('supportflow.ticket.return_review_order');
        $this->returnReviewAfterTicketId = (int) session('supportflow.harbor.restart_ticket_id', 0);
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

        if (RateLimiter::tooManyAttempts($key, (int) config('supportflow.rate_limits.tickets_per_minute'))) {
            $this->addError('subject', 'Too many tickets from this network. Please wait a minute.');

            return null;
        }

        RateLimiter::hit($key, 60);

        try {
            $data = [
                'customer_name' => $this->customer_name,
                'customer_email' => $this->customer_email,
                'subject' => $this->subject,
                'description' => $this->description,
                'product' => $this->product !== '' ? $this->product : null,
            ];
            $session = $this->demoSession();
            $ticket = $this->returnReviewOrder === null
                ? $tickets->createVisitorTicket($data, $session)
                : $tickets->createReturnReviewTicket($data, $session, $this->returnReviewOrder, $this->returnReviewAfterTicketId);
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
            'capMessage',
        ]);
        $this->forgetTicketDraft();

        session([
            'supportflow.harbor.center' => 'status',
            'supportflow.harbor.status_token' => $ticket->public_token,
        ]);

        return $this->redirectRoute('demo.harbor', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.pages.ticket-create');
    }

    protected function forgetTicketDraft(): void
    {
        Session::forget(self::DRAFT_SESSION_KEYS);
    }
}
