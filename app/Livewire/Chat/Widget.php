<?php

namespace App\Livewire\Chat;

use App\Livewire\Concerns\HeartbeatsDemoSession;
use App\Models\KnowledgeChunk;
use App\Services\ChatService;
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
        }
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
    public function escalate(ChatService $chat): void
    {
        $transcript = $chat->conversationFor($this->demoSession())
            ->messages()
            ->orderBy('id')
            ->get()
            ->map(fn ($message): string => ($message->role === 'user' ? 'Customer' : 'Assistant').': '.$message->body)
            ->implode("\n\n");

        if ($transcript !== '') {
            session([
                'supportflow.ticket.subject' => 'Follow-up from Harbor chat',
                'supportflow.ticket.description' => Str::limit("Conversation from the Harbor support demo:\n\n".$transcript, 4000, ''),
            ]);
        }

        if ($this->inline) {
            $this->dispatch('harbor-open-ticket');

            return;
        }

        $this->redirectRoute('tickets.create', navigate: true);
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
    public function stopGenerating(ChatService $chat): void
    {
        $chat->interruptStream($this->demoSession());
        $this->streaming = false;
        $this->streamText = '';

        if ($chat->recordStoppedIfOrphaned($this->demoSession()) === null && $this->pendingQuestion !== '') {
            $this->question = $this->pendingQuestion;
        }

        $this->pendingQuestion = '';
    }

    public function render(ChatService $chat): View
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

        return view('livewire.chat.widget', [
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
