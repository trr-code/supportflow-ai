<?php

namespace App\Livewire\Pages;

use App\Enums\ReplacementProposalStatus;
use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Models\ReplacementCase;
use App\Models\ReplacementProposal;
use App\Services\ReplacementCaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ReplacementTools extends Component
{
    use HeartbeatsDemoSession;

    public string $draft = '';

    public string $notice = '';

    public function ask(ReplacementCaseService $replacements): void
    {
        $this->notice = '';
        $validated = $this->validate([
            'draft' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        $session = $this->demoSession();
        $key = 'replacement:'.$session->id;
        $limit = (int) config('supportflow.replacement.per_minute');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $this->notice = 'Please wait a moment and try again.';

            return;
        }

        RateLimiter::hit($key, 60);

        $case = $replacements->forSession($session);
        $result = $replacements->queueVisitorMessage($case, $validated['draft']);

        $this->notice = match ($result) {
            'busy' => 'Please wait for the assistant to finish.',
            'pending' => 'Confirm or cancel the replacement before asking something else.',
            'capped' => 'This demo is capped at '.config('supportflow.replacement.message_cap').' questions. Reset to start fresh.',
            'queued', 'refused' => '',
            default => '',
        };

        if (in_array($result, ['queued', 'refused'], true)) {
            $this->draft = '';
        }
    }

    public function fillExample(string $key, ReplacementCaseService $replacements): void
    {
        $this->draft = match ($key) {
            'late' => 'My bag arrived damaged. Order HB-33001. Can you replace it?',
            'stock' => 'My bag arrived damaged. Order HB-33002. Can you replace it?',
            default => 'My bag arrived damaged. Can you replace it?',
        };

        $this->ask($replacements);
    }

    public function confirm(ReplacementCaseService $replacements): void
    {
        $this->notice = '';
        $proposal = $this->openProposal($replacements);

        if (! $proposal instanceof ReplacementProposal) {
            return;
        }

        if (! $replacements->beginConfirm($proposal)) {
            $this->notice = 'That proposal is no longer waiting.';
        }
    }

    public function cancel(ReplacementCaseService $replacements): void
    {
        $this->notice = '';
        $proposal = $this->openProposal($replacements);

        if (! $proposal instanceof ReplacementProposal) {
            return;
        }

        if (! $replacements->beginCancel($proposal)) {
            $this->notice = 'That proposal is no longer waiting.';
        }
    }

    public function resetDemo(ReplacementCaseService $replacements): void
    {
        $replacements->reset($replacements->forSession($this->demoSession()));
        $this->draft = '';
        $this->notice = 'Reset clears this demo and starts fresh.';
    }

    public function render(ReplacementCaseService $replacements): View
    {
        $case = $replacements->forSession($this->demoSession());
        $replacements->expirePending($case);

        $case->load([
            'messages' => fn ($query) => $query->where('generation', $case->generation)->orderBy('id'),
            'activities' => fn ($query) => $query->where('generation', $case->generation)->orderBy('id'),
            'requests' => fn ($query) => $query->latest('created_at'),
        ]);

        $proposal = $case->proposals()
            ->where('generation', $case->generation)
            ->where('status', ReplacementProposalStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();

        $confirming = $case->proposals()
            ->where('generation', $case->generation)
            ->where('status', ReplacementProposalStatus::Confirming)
            ->exists();

        return view('livewire.pages.replacement-tools', [
            'case' => $case,
            'proposal' => $proposal,
            'saved' => $case->requests->first(),
            'busy' => $case->isBusy(),
            'statusLine' => $this->statusLine($case, $confirming),
        ])->layout('components.layouts.public', [
            'title' => 'Replace a damaged bag',
        ]);
    }

    private function openProposal(ReplacementCaseService $replacements): ?ReplacementProposal
    {
        $case = $replacements->forSession($this->demoSession());

        return $case->proposals()
            ->where('generation', $case->generation)
            ->where('status', ReplacementProposalStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();
    }

    private function statusLine(ReplacementCase $case, bool $confirming): ?string
    {
        if ($confirming && $case->isBusy()) {
            return 'Saving your replacement request';
        }

        $latest = $case->activities->last();

        if ($case->isBusy()) {
            return $latest->summary ?? 'Checking your order';
        }

        return null;
    }
}
