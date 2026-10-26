<?php

namespace App\Livewire\Pages;

use App\Enums\ReplacementProposalStatus;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeChunk;
use App\Models\ReplacementProposal;
use App\Models\SupplierInvoice;
use App\Models\Ticket;
use App\Models\WorkspaceDocument;
use App\Services\ChatService;
use App\Services\DemoSessionService;
use App\Services\ReplacementCaseService;
use App\Services\SupplierInvoiceAccess;
use App\Services\WorkspaceAccess;
use App\Support\ChatInlineCitations;
use App\Support\CitedSources;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class DemoWorkspace extends Component
{
    public string $mode = 'harbor';

    public string $notice = '';

    public string $sideTab = 'instructions';

    public string $center = 'chat';

    public bool $prepareTicket = false;

    public bool $startBlank = false;

    public int $ticketFormKey = 0;

    public ?string $statusToken = null;

    public ?string $ticketReference = null;

    public ?int $inspectedMessageId = null;

    public ?int $inspectedChunkId = null;

    /** @var list<string> */
    public array $expandedSourceIds = [];

    /** @var Collection<int, ChatMessage>|null */
    private ?Collection $harborMessageCache = null;

    /** @var Collection<int, KnowledgeChunk>|null */
    private ?Collection $harborChunkCache = null;

    /** @var array<string, bool> */
    public array $openGuides = [
        'ask' => true,
        'workflow' => false,
        'safety' => false,
    ];

    #[On('harbor-thread-changed')]
    public function refreshHarborPanel(): void {}

    #[On('harbor-open-ticket')]
    public function openEscalatedTicket(): void
    {
        $this->startBlank = false;
        $this->showTicketForm(false);
    }

    public function showSideTab(string $tab): void
    {
        if (! in_array($tab, ['instructions', 'sources'], true)) {
            return;
        }

        $this->sideTab = $tab;
    }

    public function toggleGuide(string $section): void
    {
        if (! array_key_exists($section, $this->openGuides)) {
            return;
        }

        $this->openGuides[$section] = ! $this->openGuides[$section];
    }

    public function openPreparedTicket(): void
    {
        $this->startBlank = false;
        $this->showTicketForm(true);
    }

    public function openOwnTicket(): void
    {
        $this->startBlank = true;
        $this->showTicketForm(false);
    }

    public function returnToChat(): void
    {
        $this->center = 'chat';
        session(['supportflow.harbor.center' => 'chat']);
    }

    public function showCustomerReply(): void
    {
        if ($this->mode !== 'harbor' || $this->statusToken === null) {
            return;
        }

        $this->center = 'status';
        $this->openGuides['workflow'] = true;
        $this->sideTab = 'instructions';
        session(['supportflow.harbor.center' => 'status']);
    }

    public function toggleSource(string $articleId): void
    {
        if ($this->mode !== 'harbor' || $articleId === '') {
            return;
        }

        if (in_array($articleId, $this->expandedSourceIds, true)) {
            $this->expandedSourceIds = array_values(array_diff($this->expandedSourceIds, [$articleId]));

            return;
        }

        $this->expandedSourceIds[] = $articleId;
    }

    #[On('harbor-inspect-citation')]
    public function inspectCitation(int $messageId, string $articleId, int $chunkId): void
    {
        if ($this->mode !== 'harbor' || $messageId < 1 || $articleId === '' || $chunkId < 1) {
            return;
        }

        $matches = false;

        foreach ($this->groupsForMessage($messageId) as $group) {
            if ((string) $group['article_id'] !== $articleId) {
                continue;
            }

            foreach ($group['passages'] as $passage) {
                if ($passage['chunk_id'] === $chunkId) {
                    $matches = true;
                    break 2;
                }
            }
        }

        if (! $matches) {
            return;
        }

        $this->sideTab = 'sources';
        $this->inspectedMessageId = $messageId;
        $this->inspectedChunkId = $chunkId;

        if (! in_array($articleId, $this->expandedSourceIds, true)) {
            $this->expandedSourceIds[] = $articleId;
        }
    }

    public function trySafetyExample(string $key): void
    {
        if ($this->mode !== 'harbor' || ! in_array($key, ['prompt_injection', 'knowledge_gap'], true)) {
            return;
        }

        $this->center = 'chat';
        $this->openGuides['safety'] = true;
        $this->sideTab = 'instructions';
        session(['supportflow.harbor.center' => 'chat']);
        $this->dispatch('demo-fill-chat', key: $key);
    }

    public function mount(): void
    {
        $this->mode = match (request()->route()?->getName()) {
            'demo.documents' => 'documents',
            'demo.invoices' => 'invoices',
            'demo.replacement' => 'replacement',
            default => 'harbor',
        };

        $this->restoreHarborWalkthrough();
    }

    private function showTicketForm(bool $prepare): void
    {
        if ($this->mode !== 'harbor') {
            return;
        }

        $this->prepareTicket = $prepare;
        $this->ticketFormKey++;
        $this->center = 'ticket';
        $this->openGuides['workflow'] = true;
        $this->sideTab = 'instructions';
        session([
            'supportflow.harbor.center' => 'ticket',
            'supportflow.harbor.prepare_ticket' => $prepare,
        ]);
    }

    private function restoreHarborWalkthrough(): void
    {
        if ($this->mode !== 'harbor') {
            return;
        }

        $token = session('supportflow.harbor.status_token');

        if (is_string($token) && $token !== '') {
            $ticket = Ticket::query()->where('public_token', $token)->first();

            if ($ticket instanceof Ticket) {
                $this->statusToken = $ticket->public_token;
                $this->ticketReference = $ticket->reference;
                $this->openGuides['workflow'] = true;
            } else {
                session()->forget([
                    'supportflow.harbor.status_token',
                    'supportflow.harbor.center',
                ]);
            }
        }

        $center = session('supportflow.harbor.center');

        if ($center === 'status' && $this->statusToken !== null) {
            $this->center = 'status';
        }

        if ($center === 'ticket') {
            $this->center = 'ticket';
            $this->prepareTicket = (bool) session('supportflow.harbor.prepare_ticket', false);
            $this->openGuides['workflow'] = true;
        }
    }

    public function confirmReplacement(ReplacementCaseService $replacements): void
    {
        $this->notice = '';
        $proposal = $this->pendingProposal();

        if (! $proposal instanceof ReplacementProposal) {
            return;
        }

        if (! $replacements->beginConfirm($proposal)) {
            $this->notice = 'That proposal is no longer waiting.';
        }
    }

    public function cancelReplacement(ReplacementCaseService $replacements): void
    {
        $this->notice = '';
        $proposal = $this->pendingProposal();

        if (! $proposal instanceof ReplacementProposal) {
            return;
        }

        if (! $replacements->beginCancel($proposal)) {
            $this->notice = 'That proposal is no longer waiting.';
        }
    }

    public function render(): View
    {
        $titles = [
            'harbor' => 'Harbor support',
            'documents' => 'Your documents',
            'invoices' => 'Invoice review',
            'replacement' => 'Replacements',
        ];

        $harborSources = $this->harborSources();

        return view('livewire.pages.demo-workspace', [
            'title' => $titles[$this->mode],
            'proposal' => $this->pendingProposal(),
            'sourceGroups' => $harborSources['groups'],
            'sourceMessageId' => $harborSources['message_id'],
            'documents' => $this->sideDocuments(),
            'invoice' => $this->sideInvoice(),
            'safetyExamples' => $this->mode === 'harbor' ? $this->safetyExamples() : [],
        ])->layout('components.layouts.lucid', [
            'title' => $titles[$this->mode],
        ]);
    }

    /**
     * @return list<array{key: string, title: string, summary: string, expect: string, button: string, promptKey: ?string}>
     */
    private function safetyExamples(): array
    {
        return [
            [
                'key' => 'unsafe_instruction',
                'title' => 'Unsafe request',
                'summary' => 'Someone asks the assistant to ignore its rules.',
                'expect' => 'A clear refusal. It does not reveal hidden instructions.',
                'button' => 'Try',
                'promptKey' => 'prompt_injection',
            ],
            [
                'key' => 'missing_knowledge',
                'title' => 'Missing answer',
                'summary' => 'Someone asks for something the policies do not cover.',
                'expect' => 'It says the store does not offer that and does not invent details.',
                'button' => 'Try',
                'promptKey' => 'knowledge_gap',
            ],
            [
                'key' => 'agent_scenarios',
                'title' => 'Agent scenarios',
                'summary' => 'Nine example tickets are ready for a person to review.',
                'expect' => 'Agent desk opens on those examples. The samples already there stay as they are.',
                'button' => 'Open Agent scenarios',
                'promptKey' => null,
            ],
        ];
    }

    private function pendingProposal(): ?ReplacementProposal
    {
        if (! in_array($this->mode, ['harbor', 'replacement'], true)) {
            return null;
        }

        $session = $this->demoSession();

        if (! $session instanceof DemoSession) {
            return null;
        }

        $case = $session->replacementCase;

        if ($case === null) {
            return null;
        }

        app(ReplacementCaseService::class)->expirePending($case);

        return $case->proposals()
            ->where('generation', $case->generation)
            ->where('status', ReplacementProposalStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @return array{message_id: int|null, groups: list<array{article_id: string, title: string, headings: list<string>, passages: list<array{number: int, chunk_id: int, excerpt: string}>}>}
     */
    private function harborSources(): array
    {
        $empty = ['message_id' => null, 'groups' => []];

        if ($this->mode !== 'harbor') {
            return $empty;
        }

        $message = $this->selectedHarborMessage();

        if (! $message instanceof ChatMessage) {
            return $empty;
        }

        return [
            'message_id' => $message->id,
            'groups' => $this->groupsForMessage($message->id),
        ];
    }

    private function selectedHarborMessage(): ?ChatMessage
    {
        $messages = $this->harborMessages();

        if ($this->inspectedMessageId !== null) {
            $selected = $messages->firstWhere('id', $this->inspectedMessageId);

            if ($selected instanceof ChatMessage) {
                return $selected;
            }
        }

        return $messages->reverse()->first(fn (ChatMessage $message): bool => $this->groupsForMessage($message->id) !== []);
    }

    /**
     * @return list<array{article_id: string, title: string, headings: list<string>, passages: list<array{number: int, chunk_id: int, excerpt: string}>}>
     */
    private function groupsForMessage(int $messageId): array
    {
        $message = $this->harborMessages()->firstWhere('id', $messageId);

        if (! $message instanceof ChatMessage) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = array_values(array_map(intval(...), $message->cited_chunk_ids ?? []));
        $chunks = $this->harborChunks()->only($ids);

        return ChatInlineCitations::groups(
            $message->body,
            CitedSources::inCitationOrder($chunks, $ids)->all(),
        );
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    private function harborMessages(): Collection
    {
        if ($this->harborMessageCache instanceof Collection) {
            return $this->harborMessageCache;
        }

        $session = $this->demoSession();

        if (! $session instanceof DemoSession) {
            return $this->harborMessageCache = collect();
        }

        return $this->harborMessageCache = app(ChatService::class)
            ->conversationFor($session)
            ->messages()
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, KnowledgeChunk>
     */
    private function harborChunks(): Collection
    {
        if ($this->harborChunkCache instanceof Collection) {
            return $this->harborChunkCache;
        }

        $citedIds = [];

        foreach ($this->harborMessages() as $message) {
            foreach ($message->cited_chunk_ids ?? [] as $id) {
                $citedIds[] = (int) $id;
            }
        }

        $citedIds = array_values(array_unique(array_filter($citedIds)));

        if ($citedIds === []) {
            return $this->harborChunkCache = collect();
        }

        return $this->harborChunkCache = KnowledgeChunk::query()
            ->with('article')
            ->whereIn('id', $citedIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function sideDocuments(): array
    {
        if ($this->mode !== 'documents') {
            return [];
        }

        $workspace = app(WorkspaceAccess::class)->current(request());

        if ($workspace === null) {
            return [];
        }

        return array_values($workspace->documents()
            ->latest()
            ->get(['id', 'original_name'])
            ->map(fn (WorkspaceDocument $document): array => [
                'id' => $document->id,
                'name' => $document->original_name,
            ])
            ->all());
    }

    private function sideInvoice(): ?SupplierInvoice
    {
        if ($this->mode !== 'invoices') {
            return null;
        }

        return app(SupplierInvoiceAccess::class)->latest(request());
    }

    private function demoSession(): ?DemoSession
    {
        $id = request()->cookie(DemoSessionService::COOKIE);

        if (! is_string($id) || $id === '') {
            return null;
        }

        $session = DemoSession::query()->find($id);

        return $session instanceof DemoSession ? $session : null;
    }
}
