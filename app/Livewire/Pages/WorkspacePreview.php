<?php

namespace App\Livewire\Pages;

use App\Enums\WorkspaceAnswerLength;
use App\Enums\WorkspaceDocumentStatus;
use App\Enums\WorkspaceGuidanceCategory;
use App\Enums\WorkspaceTone;
use App\Exceptions\WorkspaceUploadException;
use App\Models\ChatMessage;
use App\Models\KnowledgeChunk;
use App\Models\Workspace;
use App\Models\WorkspaceDocument;
use App\Models\WorkspaceGuidance;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceChatService;
use App\Services\WorkspaceDocumentStore;
use App\Services\WorkspacePurgeService;
use App\Support\ChatAnswerHtml;
use App\Support\CitedSources;
use App\Support\WorkspaceCopy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class WorkspacePreview extends Component
{
    use WithFileUploads;

    public string $question = '';

    public string $returnInput = '';

    public string $tone = WorkspaceTone::Professional->value;

    public string $answerLength = WorkspaceAnswerLength::Standard->value;

    /** @var list<array{key: string, category: string, body: string}> */
    public array $guidanceNotes = [];

    /** @var list<TemporaryUploadedFile> */
    public array $uploads = [];

    public bool $streaming = false;

    public ?string $pendingQuestion = null;

    public function mount(WorkspaceAccess $access): void
    {
        $this->fillFromWorkspace($access);
    }

    public function start(WorkspaceAccess $access): void
    {
        $access->start();
        $this->returnInput = '';
        $this->fillFromWorkspace($access);
    }

    public function openWithCode(WorkspaceAccess $access): void
    {
        $this->validate([
            'returnInput' => ['required', 'string', 'max:80'],
        ]);

        try {
            $access->returnWithCode(request(), $this->returnInput);
        } catch (WorkspaceUploadException $exception) {
            $this->addError('returnInput', $exception->getMessage());

            return;
        }

        $this->returnInput = '';
        $this->fillFromWorkspace($access);
    }

    public function dismissReturnCode(WorkspaceAccess $access): void
    {
        $access->dismissReturnCode();
    }

    public function saveControls(WorkspaceAccess $access): void
    {
        $workspace = $access->current(request());

        if ($workspace === null) {
            return;
        }

        $this->validate([
            'tone' => ['required', Rule::enum(WorkspaceTone::class)],
            'answerLength' => ['required', Rule::enum(WorkspaceAnswerLength::class)],
            'guidanceNotes' => ['array', 'max:5'],
            'guidanceNotes.*.category' => ['required', Rule::enum(WorkspaceGuidanceCategory::class)],
            'guidanceNotes.*.body' => ['nullable', 'string', 'max:800'],
        ]);

        $workspace->forceFill([
            'tone' => $this->tone,
            'answer_length' => $this->answerLength,
        ])->save();

        $workspace->guidances()->delete();

        $position = 0;

        foreach ($this->guidanceNotes as $note) {
            $body = trim($note['body']);

            if ($body === '') {
                continue;
            }

            $workspace->guidances()->create([
                'category' => $note['category'],
                'body' => $body,
                'position' => $position,
            ]);
            $position++;
        }

        $this->fillFromWorkspace($access);
    }

    public function addGuidance(): void
    {
        if (count($this->guidanceNotes) >= 5) {
            return;
        }

        $this->guidanceNotes[] = [
            'key' => (string) Str::uuid(),
            'category' => WorkspaceGuidanceCategory::Wording->value,
            'body' => '',
        ];
    }

    public function removeGuidance(string $key): void
    {
        $this->guidanceNotes = array_values(array_filter(
            $this->guidanceNotes,
            fn (array $note): bool => $note['key'] !== $key,
        ));
    }

    public function storeDocuments(WorkspaceAccess $access, WorkspaceDocumentStore $store): void
    {
        $workspace = $access->current(request());

        if ($workspace === null) {
            return;
        }

        try {
            $store->storeMany($workspace, array_values($this->uploads));
            $this->reset('uploads');
        } catch (WorkspaceUploadException $exception) {
            $this->addError('uploads', $exception->getMessage());
        }
    }

    public function removeDocument(string $documentId, WorkspaceAccess $access, WorkspaceDocumentStore $store): void
    {
        $workspace = $access->current(request());
        $document = WorkspaceDocument::query()->find($documentId);

        if ($workspace === null || ! $document instanceof WorkspaceDocument) {
            return;
        }

        try {
            $store->delete($workspace, $document);
        } catch (WorkspaceUploadException $exception) {
            $this->addError('uploads', $exception->getMessage());
        }
    }

    public function leave(WorkspaceAccess $access): void
    {
        $access->leave(request());
        $this->resetWorkspaceState();
    }

    public function deleteWorkspace(WorkspaceAccess $access, WorkspacePurgeService $purge): void
    {
        $workspace = $access->current(request());

        if ($workspace !== null) {
            $purge->purge($workspace);
        }

        $access->leave(request());
        $this->resetWorkspaceState();
    }

    public function send(): void
    {
        $this->validate([
            'question' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        $this->pendingQuestion = trim($this->question);
        $this->question = '';
        $this->streaming = true;
    }

    public function finishTurn(): void
    {
        $this->streaming = false;
        $this->pendingQuestion = null;
    }

    public function requestStop(WorkspaceAccess $access, WorkspaceChatService $chat): void
    {
        $workspace = $access->current(request());

        if ($workspace !== null) {
            $chat->requestStop($workspace);
        }
    }

    public function newChat(WorkspaceAccess $access, WorkspaceChatService $chat): void
    {
        $workspace = $access->current(request());

        if ($workspace === null) {
            return;
        }

        $chat->startNewConversation($workspace);
        $this->finishTurn();
    }

    public function render(WorkspaceAccess $access, WorkspaceChatService $chat): View
    {
        $workspace = $access->current(request());
        $documents = $workspace?->documents()->latest()->get() ?? collect();
        $indexing = $documents->contains(fn (WorkspaceDocument $document): bool => in_array($document->status, [
            WorkspaceDocumentStatus::Queued,
            WorkspaceDocumentStatus::Processing,
        ], true));

        return view('livewire.pages.workspace-preview', [
            'workspace' => $workspace,
            'documents' => $documents,
            'indexing' => $indexing,
            'returnCode' => $workspace ? $access->displayedReturnCode($workspace) : null,
            'expiresOn' => $workspace?->expires_at->timezone(config('app.timezone'))->format('F j, Y'),
            'messages' => $workspace ? $this->transcript($chat, $workspace) : [],
            'disclosure' => WorkspaceCopy::DISCLOSURE,
            'leaveCopy' => WorkspaceCopy::LEAVE,
            'deleteCopy' => WorkspaceCopy::DELETE,
            'failureCopy' => WorkspaceCopy::FAILURE,
            'tones' => WorkspaceTone::cases(),
            'lengths' => WorkspaceAnswerLength::cases(),
            'categories' => WorkspaceGuidanceCategory::cases(),
        ])->layout('components.layouts.workspace', [
            'title' => 'Private preview',
        ]);
    }

    /**
     * @return list<array{id: int, role: string, html: string, sources: list<array{chunk_id: int, title: string, heading: string|null, excerpt: string}>}>
     */
    private function transcript(WorkspaceChatService $chat, Workspace $workspace): array
    {
        return array_values($chat->conversationFor($workspace)
            ->messages()
            ->orderBy('id')
            ->get()
            ->map(function (ChatMessage $message): array {
                $citedIds = array_values(array_map(intval(...), $message->cited_chunk_ids ?? []));

                return [
                    'id' => (int) $message->id,
                    'role' => $message->role,
                    'html' => ChatAnswerHtml::render($message->body),
                    'sources' => $citedIds === []
                        ? []
                        : CitedSources::workspaceInspector(
                            KnowledgeChunk::query()->with('article')->whereIn('id', $citedIds)->get(),
                            $citedIds,
                        ),
                ];
            })
            ->all());
    }

    private function fillFromWorkspace(WorkspaceAccess $access): void
    {
        $workspace = $access->current(request());

        if ($workspace === null) {
            $this->resetWorkspaceState();

            return;
        }

        $this->tone = $workspace->tone->value;
        $this->answerLength = $workspace->answer_length->value;
        $this->guidanceNotes = array_values($workspace->guidances()
            ->orderBy('position')
            ->get()
            ->map(fn (WorkspaceGuidance $note): array => [
                'key' => (string) $note->id,
                'category' => $note->category->value,
                'body' => $note->body,
            ])
            ->all());
    }

    private function resetWorkspaceState(): void
    {
        $this->tone = WorkspaceTone::Professional->value;
        $this->answerLength = WorkspaceAnswerLength::Standard->value;
        $this->guidanceNotes = [];
        $this->uploads = [];
        $this->question = '';
        $this->pendingQuestion = null;
        $this->streaming = false;
    }
}
