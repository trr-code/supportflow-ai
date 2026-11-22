<?php

namespace App\Livewire\Chat;

use App\Exceptions\DemoCapReachedException;
use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Models\ChatMessage;
use App\Models\KnowledgeChunk;
use App\Services\ChatService;
use App\Services\HarborChatHandoff;
use App\Services\HarborReturns;
use App\Services\TicketService;
use App\Support\CitedSources;
use App\Support\DemoGuide;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Async;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Widget extends Component
{
    use HeartbeatsDemoSession;

    #[Validate('required|string|min:4|max:500')]
    public string $question = '';

    public string $pendingQuestion = '';

    public bool $open = false;

    public bool $inline = false;

    public bool $streaming = false;

    public string $streamText = '';

    public function mount(): void
    {
        if ($this->inline) {
            $this->open = true;

            if (request()->query('example') === 'replacement') {
                $this->fillReplacementExample();
            }
        }
    }

    #[On('harbor-fill-replacement')]
    public function fillReplacementExample(): void
    {
        if ($this->streaming) {
            return;
        }

        $this->question = 'My bag arrived damaged. Order HB-10482. Can you replace it?';
        $this->open = true;
        $this->dispatch('demo-chat-focus');
    }

    public function send(ChatService $chat): void
    {
        $this->validate();

        if ($this->streaming) {
            return;
        }

        $chat->clearStopRequest($this->demoSession());
        $this->pendingQuestion = $this->question;
        $this->question = '';
        $this->open = true;
        $this->streaming = true;
        $this->streamText = '';
        $this->js('$js.startStream()');
    }

    public function updatedQuestion(): void
    {
        $this->resetErrorBag('question');
    }

    public function fillReturnExample(string $number, HarborReturns $returns): void
    {
        if (! $this->inline || $this->streaming || ! isset($returns->orders()[$number])) {
            return;
        }

        $this->question = 'I would like to start a return for order '.$number.'.';
        $this->resetErrorBag('question');
        $this->dispatch('demo-chat-focus');
    }

    public function confirmReturn(string $number, ChatService $chat, HarborReturns $returns): void
    {
        if (! $this->inline || $this->streaming) {
            return;
        }

        $session = $this->demoSession();
        $conversation = $chat->conversationFor($session);
        $question = (string) $conversation->messages()->where('role', 'user')->latest('id')->value('body');
        $result = $returns->assess($session, $number);
        $latest = $conversation->messages()->latest('id')->first();

        $previousReply = $conversation->messages()->where('role', 'assistant')->where('id', '<', $latest->id ?? 0)->latest('id')->value('body');

        if ($number !== $returns->requestedOrder($question, $previousReply) || $result['status'] !== 'eligible'
            || $latest?->role !== 'assistant' || ! $returns->matchesReply($latest->body, $result['message'], $question)) {
            return;
        }

        $result = $returns->confirm($session, $number);
        $conversation->messages()->create([
            'role' => 'assistant',
            'body' => $result['message'],
            'cited_chunk_ids' => [],
        ]);
        $this->notifyWorkspace();
    }

    public function submitReturnReview(ChatService $chat, HarborChatHandoff $handoff, TicketService $tickets): void
    {
        if (! $this->inline || $this->streaming) {
            return;
        }
        $session = $this->demoSession();
        $conversation = $chat->conversationFor($session);
        $messages = $conversation->messages()->orderBy('id')->get();
        $proposal = $handoff->proposal($messages);
        $sampleReview = app(HarborReturns::class)->currentReply($session, $messages);
        if ($proposal === null || $proposal['order'] === ''
            || ($messages->last()?->body !== HarborChatHandoff::REVIEW && $sampleReview['status'] !== 'review')) {
            return;
        }
        try {
            $ticket = $tickets->createReturnReviewTicket([
                'customer_name' => 'Demo customer',
                'customer_email' => 'customer@example.test',
                'subject' => '',
                'description' => $proposal['description'],
            ], $session, $proposal['order']);
        } catch (DemoCapReachedException $exception) {
            $this->addError('question', $exception->getMessage());

            return;
        }
        $conversation->messages()->create([
            'role' => 'assistant',
            'body' => 'Your support request has been submitted for review. Your reference is '.$ticket->reference.'. You can follow the reply using View support request.',
            'cited_chunk_ids' => [],
        ]);
        $this->notifyWorkspace();
    }

    public function reportStreamError(string $message): void
    {
        if ($this->pendingQuestion !== '') {
            $this->question = $this->pendingQuestion;
        }

        $this->pendingQuestion = '';
        $this->streaming = false;
        $this->streamText = '';
        $this->addError('question', $message);
    }

    public function abandonFailedStream(string $message, ChatService $chat): void
    {
        $chat->interruptStream($this->demoSession());
        $this->reportStreamError($message);
    }

    #[On('request-harbor-escalate')]
    public function escalate(ChatService $chat, HarborReturns $returns, TicketService $tickets): mixed
    {
        $session = $this->demoSession();
        $messages = $chat->conversationFor($session)->messages()->orderBy('id')->get();
        $return = $this->inline ? $returns->currentReply($session, $messages) : null;
        $order = ($return['status'] ?? null) === 'review' ? $return['order'] : null;

        if ($order !== null && ($existing = $tickets->returnReviewTicket($session, $order)) !== null) {
            session([
                'supportflow.harbor.center' => 'status',
                'supportflow.harbor.status_token' => $existing->public_token,
            ]);

            return $this->redirectRoute('demo.harbor', navigate: true);
        }

        $transcript = $messages
            ->map(fn ($message): string => ($message->role === 'user' ? 'Customer' : 'Assistant').': '.$message->body)
            ->implode("\n\n");

        if ($transcript !== '') {
            session([
                'supportflow.ticket.return_review_order' => $order,
                'supportflow.ticket.customer_name' => 'Maya Chen',
                'supportflow.ticket.customer_email' => 'maya.chen@example.test',
                'supportflow.ticket.subject' => 'Follow-up from Harbor chat',
                'supportflow.ticket.description' => Str::limit("Conversation from Harbor support:\n\n".$transcript, 4000, ''),
            ]);
        }

        if ($this->inline) {
            $this->dispatch('harbor-open-ticket');

            return null;
        }

        return $this->redirectRoute('demo.harbor', ['view' => 'ticket'], navigate: true);
    }

    public function finishTurn(): void
    {
        $this->pendingQuestion = '';
        $this->streaming = false;
        $this->streamText = '';
        $this->notifyWorkspace();
    }

    #[On('demo-open-chat')]
    public function openChat(): void
    {
        if ($this->streaming) {
            return;
        }

        $this->open = true;
        $this->dispatch('demo-chat-focus');
    }

    #[On('demo-fill-chat')]
    public function fillQuestion(string $key): void
    {
        if ($this->streaming) {
            return;
        }

        $prompt = DemoGuide::prompt($key);

        if ($prompt === null) {
            return;
        }

        $this->question = $prompt['question'];
        $this->open = true;
        $this->resetErrorBag('question');
        $this->dispatch('demo-chat-focus');
        $this->js(<<<'JS'
            const focusChatQuestion = () => {
                const root = document.getElementById('chat-question');
                if (root === null) {
                    return false;
                }
                const input = (root instanceof HTMLInputElement || root instanceof HTMLTextAreaElement)
                    ? root
                    : root.querySelector('input, textarea');
                if (! (input instanceof HTMLInputElement) && ! (input instanceof HTMLTextAreaElement)) {
                    return false;
                }
                input.focus();
                return document.activeElement === input;
            };
            if (! focusChatQuestion()) {
                requestAnimationFrame(() => {
                    if (! focusChatQuestion()) {
                        requestAnimationFrame(focusChatQuestion);
                    }
                });
            }
        JS);
    }

    public function startNewConversation(ChatService $chat): void
    {
        if ($this->streaming) {
            return;
        }

        $chat->startNewConversation($this->demoSession());
        $this->question = '';
        $this->pendingQuestion = '';
        $this->streamText = '';
        $this->dispatch('modal-close', name: 'confirm-new-conversation');
        $this->notifyWorkspace();
    }

    #[Async]
    public function stopGenerating(ChatService $chat, ?int $completedId = null, int $revealedCharacters = 0): void
    {
        if ($completedId !== null) {
            $chat->conversationFor($this->demoSession())->messages()->where('role', 'assistant')
                ->whereKey($completedId)->first()?->keepRevealedPrefix($revealedCharacters);
            $this->finishTurn();

            return;
        }

        $chat->interruptStream($this->demoSession());
        $this->streaming = false;
        $this->streamText = '';

        if ($chat->recordStoppedIfOrphaned($this->demoSession()) === null && $this->pendingQuestion !== '') {
            $this->question = $this->pendingQuestion;
        }

        $this->pendingQuestion = '';
    }

    public function render(ChatService $chat, HarborReturns $returns): View
    {
        $conversation = $chat->conversationFor($this->demoSession());
        $messages = $conversation->messages()->orderBy('id')->get();
        $citedIds = $messages
            ->pluck('cited_chunk_ids')
            ->flatten()
            ->unique()
            ->filter()
            ->values()
            ->all();
        $chunks = $citedIds === []
            ? collect()
            : KnowledgeChunk::query()->with('article')->whereIn('id', $citedIds)->get()->keyBy('id');

        $sourceGroups = [];
        $citedChunks = [];

        foreach ($messages as $message) {
            /** @var list<int> $ids */
            $ids = array_values(array_map(intval(...), $message->cited_chunk_ids ?? []));
            $ordered = CitedSources::inCitationOrder($chunks->only($ids), $ids);
            $sourceGroups[$message->id] = CitedSources::groupByArticle($ordered);
            $citedChunks[$message->id] = $ordered->all();
        }

        $return = $this->inline && ! $this->streaming
            ? $returns->currentReply($this->demoSession(), $messages)
            : ['order' => null, 'status' => null];
        $latest = $messages->last();
        $handoff = $this->inline ? app(HarborChatHandoff::class)->proposal($messages) : null;

        return view('livewire.chat.widget', [
            'handoff' => $handoff,
            'handoffTicket' => $handoff !== null && $handoff['order'] !== '' ? app(TicketService::class)->returnReviewTicket($this->demoSession(), $handoff['order']) : null,
            'returnOrder' => $return['order'],
            'returnMessageId' => $latest instanceof ChatMessage ? $latest->id : null,
            'returnStatus' => $return['status'],
            'returnReviewTicket' => $return['status'] === 'review' && $return['order'] !== null
                ? app(TicketService::class)->returnReviewTicket($this->demoSession(), $return['order'])
                : null,
            'conversationId' => $conversation->id,
            'messages' => $messages,
            'sourceGroups' => $sourceGroups,
            'citedChunks' => $citedChunks,
            'primaryPrompts' => DemoGuide::primaryChatPrompts(),
        ]);
    }

    private function notifyWorkspace(): void
    {
        if ($this->inline) {
            $this->dispatch('harbor-thread-changed');
        }
    }
}
