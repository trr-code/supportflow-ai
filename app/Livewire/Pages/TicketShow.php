<?php

namespace App\Livewire\Pages;

use App\Enums\AiRunFeature;
use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketDepartment;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Enums\TicketStatus;
use App\Jobs\GenerateSuggestedReply;
use App\Jobs\ProcessTicketIntake;
use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Services\AiUsageRecorder;
use App\Services\SuggestedReplyService;
use App\Services\TicketIntakeService;
use App\Services\TicketService;
use App\Support\CitedSources;
use App\Support\SuggestedReplyPanelState;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

class TicketShow extends Component
{
    use HeartbeatsDemoSession;

    /** @var list<string> */
    private const PROCESSING_FLASHES = [
        'Regenerating a grounded draft…',
        'Retrying AI intake…',
    ];

    private const UNCHANGED_REGENERATE_FLASH = 'The regenerated draft matched the previous one. The current draft was kept.';

    public Ticket $ticket;

    public string $note = '';

    public string $customReply = '';

    public string $draftBody = '';

    public ?int $draftReplyId = null;

    public bool $showTechnical = false;

    public ?string $flash = null;

    #[Validate('nullable')]
    public ?string $category = null;

    public ?string $priority = null;

    public ?string $sentiment = null;

    public ?string $department = null;

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticket = $ticket;
        $this->syncDraft();
    }

    public function saveOverrides(TicketIntakeService $intake): void
    {
        $this->authorize('update', $this->ticket);

        $intake->applyOverride($this->ticket, [
            'category' => $this->category ? TicketCategory::from($this->category) : $this->ticket->category,
            'priority' => $this->priority ? TicketPriority::from($this->priority) : $this->ticket->priority,
            'sentiment' => $this->sentiment ? TicketSentiment::from($this->sentiment) : $this->ticket->sentiment,
            'department' => $this->department ? TicketDepartment::from($this->department) : $this->ticket->department,
        ], (string) auth()->user()?->name);

        $this->ticket->refresh();
        $this->flash = 'Classification updated. AI proposal is unchanged so you can compare.';
    }

    public function saveDraft(SuggestedReplyService $replies): void
    {
        $reply = $this->pendingReply();

        if (! $reply) {
            return;
        }

        $this->authorize('update', $reply);
        $replies->updateBody($reply, $this->draftBody, (string) auth()->user()?->name);
        $this->flash = 'Draft saved. The customer cannot see it until you approve and send.';
    }

    public function approveAndSend(SuggestedReplyService $replies): void
    {
        $reply = $this->pendingReply();

        if (! $reply) {
            return;
        }

        $this->authorize('update', $reply);

        if (trim($this->draftBody) !== '') {
            $replies->updateBody($reply, $this->draftBody, (string) auth()->user()?->name);
            $reply->refresh();
        }

        $replies->approveAndSend($reply, (string) auth()->user()?->name);
        $this->ticket->refresh();
        $this->flash = 'Reply sent to the customer status page (simulated—no email).';
        $this->syncDraft();
    }

    public function reject(SuggestedReplyService $replies): void
    {
        $reply = $this->pendingReply();

        if (! $reply) {
            return;
        }

        $this->authorize('update', $reply);
        $replies->reject($reply, (string) auth()->user()?->name);
        $this->ticket->refresh();
        $this->flash = 'Suggestion rejected. Ticket escalated for a human reply.';
        $this->syncDraft();
    }

    public function regenerate(AiUsageRecorder $recorder): void
    {
        $this->authorize('update', $this->ticket);

        $max = (int) config('supportflow.rate_limits.regenerate.max_attempts', 5);
        $decay = (int) config('supportflow.rate_limits.regenerate.decay_seconds', 600);
        $key = 'regenerate|'.auth()->id().'|'.$this->ticket->id;

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $wait = now()->addSeconds(max(1, RateLimiter::availableIn($key)))
                ->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE);
            $this->flash = "Regeneration limit reached. Try again in {$wait}.";

            return;
        }

        RateLimiter::hit($key, $decay);
        $from = $this->pendingReply()?->id;
        $recorder->queue(
            AiRunFeature::SuggestedReply,
            $this->ticket,
            (string) config('supportflow.models.reply'),
        );
        GenerateSuggestedReply::dispatch($this->ticket->id, true, $from);
        $this->flash = 'Regenerating a grounded draft…';
    }

    public function retryAi(AiUsageRecorder $recorder): void
    {
        $this->authorize('update', $this->ticket);
        $recorder->queue(
            AiRunFeature::Triage,
            $this->ticket,
            (string) config('supportflow.models.triage'),
        );
        ProcessTicketIntake::dispatch($this->ticket->id, true);
        $this->flash = 'Retrying AI intake…';
    }

    public function shouldPoll(): bool
    {
        $this->ticket->refresh();
        $this->promotePendingDraftToAwaitingReview();
        $this->clearProcessingFlashWhenSettled();
        $this->applyUnchangedRegenerateNotice();

        return $this->aiWorkInFlight();
    }

    public function addNote(TicketService $tickets): void
    {
        $this->authorize('update', $this->ticket);
        $this->validate(['note' => 'required|string|max:2000']);
        $tickets->addInternalNote($this->ticket, $this->note, (string) auth()->user()?->name);
        $this->note = '';
        $this->flash = 'Internal note saved. Customers never see notes.';
    }

    public function sendCustom(SuggestedReplyService $replies): void
    {
        $this->authorize('update', $this->ticket);
        $this->validate(['customReply' => 'required|string|min:8|max:4000']);
        $replies->sendCustom($this->ticket, $this->customReply, (string) auth()->user()?->name);
        $this->customReply = '';
        $this->ticket->refresh();
        $this->flash = 'Human reply sent to the customer status page (simulated).';
    }

    public function changeStatus(string $status, TicketService $tickets): void
    {
        $this->authorize('update', $this->ticket);
        $tickets->changeStatus($this->ticket, TicketStatus::from($status), (string) auth()->user()?->name);
        $this->ticket->refresh();
    }

    public function render(): View
    {
        $this->ticket->refresh();
        $this->promotePendingDraftToAwaitingReview();
        $this->clearProcessingFlashWhenSettled();
        $this->applyUnchangedRegenerateNotice();
        $this->syncDraft();

        $events = $this->ticket->events()->with('aiRun')->latest()->get();
        $notes = $this->ticket->messages()->where('visibility', 'internal')->latest()->get();
        $public = $this->ticket->messages()->where('visibility', 'public')->whereNotNull('approved_at')->orderBy('id')->get();
        $runs = $this->ticket->aiRuns()->latest()->get();
        $reply = $this->ticket->suggestedReplies()->latest('id')->first();
        $pending = $this->pendingReply();
        $citedChunks = $pending instanceof SuggestedReply ? $pending->citedChunks() : collect();
        $citedIds = $pending instanceof SuggestedReply ? ($pending->cited_chunk_ids ?? []) : [];

        return view('livewire.pages.ticket-show', [
            'events' => $events,
            'notes' => $notes,
            'publicMessages' => $public,
            'runs' => $runs,
            'suggestedReply' => $reply,
            'pendingReply' => $pending,
            'citedSourceGroups' => CitedSources::groupByArticle(
                CitedSources::inCitationOrder($citedChunks, $citedIds),
            ),
            'replyPanel' => SuggestedReplyPanelState::for(
                $this->ticket,
                $pending,
                $reply,
                SuggestedReplyPanelState::running($this->ticket),
            ),
        ])->layout('layouts.app', ['title' => $this->ticket->reference]);
    }

    protected function pendingReply(): ?SuggestedReply
    {
        return SuggestedReply::query()
            ->where('ticket_id', $this->ticket->id)
            ->where('status', SuggestedReplyStatus::Pending)
            ->latest('id')
            ->first();
    }

    protected function promotePendingDraftToAwaitingReview(): void
    {
        if ($this->ticket->status !== TicketStatus::Triaging) {
            return;
        }

        if ($this->pendingReply() === null) {
            return;
        }

        $this->ticket->forceFill(['status' => TicketStatus::AwaitingReview])->save();
    }

    protected function aiWorkInFlight(): bool
    {
        if (in_array($this->ticket->status, [TicketStatus::Submitted, TicketStatus::Triaging], true)) {
            return true;
        }

        return SuggestedReplyPanelState::running($this->ticket);
    }

    protected function clearProcessingFlashWhenSettled(): void
    {
        if (! in_array($this->flash, self::PROCESSING_FLASHES, true)) {
            return;
        }

        if ($this->aiWorkInFlight()) {
            return;
        }

        $this->flash = null;
    }

    protected function applyUnchangedRegenerateNotice(): void
    {
        if ($this->flash !== null) {
            return;
        }

        $event = $this->ticket->events()->latest('id')->first();

        if ($event === null || $event->type !== TicketEventType::SuggestionRegenerated) {
            return;
        }

        if (($event->payload['unchanged'] ?? false) !== true) {
            return;
        }

        $this->flash = self::UNCHANGED_REGENERATE_FLASH;
    }

    protected function syncDraft(): void
    {
        $this->category = $this->ticket->category?->value;
        $this->priority = $this->ticket->priority?->value;
        $this->sentiment = $this->ticket->sentiment?->value;
        $this->department = $this->ticket->department?->value;

        $pending = $this->pendingReply();

        if ($pending === null) {
            return;
        }

        if ($this->draftBody === '' || $this->draftReplyId !== $pending->id) {
            $this->draftBody = $pending->body;
            $this->draftReplyId = $pending->id;
        }
    }
}
