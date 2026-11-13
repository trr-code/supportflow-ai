<div class="showroom-documents" @if ($indexing) wire:poll.3s.visible @endif>
    <x-lucid.page-header class="showroom-task-header" title="Document chat" heading-id="document-chat-title" badge="Document demo" description="Ask questions about your files and check the sources behind each answer." />
        <div wire:key="documents-active" class="showroom-document-tools">
            <section class="showroom-document-library" aria-labelledby="loaded-documents-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="loaded-documents-title" class="lucid-section-heading">Your documents <span class="showroom-fiction-badge">{{ $documents->count() }} {{ $documents->count() === 1 ? 'file' : 'files' }}</span></h2>
                </div>
                <p class="mt-3 text-sm text-zinc-600">Answers use the files listed here once they are ready.</p>
                <div class="showroom-document-files-body">
                    @forelse ($documents as $document)
                        <div wire:key="document-file-{{ $document->id }}" class="showroom-document-file">
                            <div class="min-w-0"><p class="break-words font-medium">{{ $document->original_name }}</p><div class="mt-2" role="status"><x-status-badge :status="$document->status->value" wire:key="document-status-{{ $document->id }}" :label="match ($document->status) { \App\Enums\WorkspaceDocumentStatus::Ready => 'Ready for answers', \App\Enums\WorkspaceDocumentStatus::Failed => 'Could not read document', default => 'Reading this document…' }" /></div>@if ($document->status === \App\Enums\WorkspaceDocumentStatus::Failed)<p class="mt-2 text-sm text-red-700">{{ $failureCopy }}</p>@endif@if ($document->failure_reason)<p class="mt-1 text-sm text-red-700">{{ $document->failure_reason }}</p>@endif</div>
                            <flux:modal.trigger name="remove-workspace-document-{{ $document->id }}"><flux:button type="button" class="shrink-0" data-document-delete>Delete</flux:button></flux:modal.trigger>
                            <flux:modal name="remove-workspace-document-{{ $document->id }}" class="max-w-lg"><div class="space-y-4"><flux:heading level="2" class="lucid-content-heading">Delete this document?</flux:heading><p class="break-words">{{ $document->original_name }}</p><p>This removes the file and its answers from this workspace. This cannot be undone.</p><div class="flex flex-wrap justify-end gap-3"><flux:modal.close><flux:button>Cancel</flux:button></flux:modal.close><flux:button wire:click="removeDocument('{{ $document->id }}')" variant="danger" class="showroom-destructive-action">Delete document permanently</flux:button></div></div></flux:modal>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">{{ $uploads === [] ? 'No documents added yet.' : 'Your selected files are not added yet. Select Upload files.' }}</p>
                    @endforelse
                    <form id="document-upload-panel" wire:submit="storeDocuments" class="showroom-document-upload space-y-4">
                        <h3 class="lucid-section-heading">Add documents</h3>
                        <p class="text-sm">OpenAI processes your documents and questions to prepare answers. Use non-sensitive files. This demo workspace expires after seven days.</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="file-choose relative inline-flex cursor-pointer rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium">Choose files<input aria-label="Choose files" type="file" wire:model="uploads" multiple accept=".pdf,.docx,.txt,.md,.markdown" class="absolute inset-0 cursor-pointer opacity-0" /></label>
                            <flux:button type="submit" size="sm" wire:key="document-upload-submit-{{ $uploads === [] ? 'empty' : 'selected' }}" :disabled="$uploads === []" wire:loading.attr="disabled" wire:target="uploads,storeDocuments">Upload files</flux:button>
                        </div>
                        <p class="text-xs text-zinc-500">PDF, DOCX, TXT or Markdown · Up to {{ (int) (config('supportflow.workspaces.max_file_bytes') / 1048576) }} MB per file, {{ (int) config('supportflow.workspaces.max_batch') }} files at a time, and {{ (int) config('supportflow.workspaces.max_documents') }} per workspace. PDFs need text you can highlight and copy. Image-only scans won’t work.</p>
                        @foreach ($uploads as $upload)<p wire:key="document-upload-{{ $upload->getFilename() }}" class="break-words text-sm">{{ $upload->getClientOriginalName() }}</p>@endforeach
                    </form>
                </div>
                <p wire:loading wire:target="uploads,storeDocuments" role="status" class="mt-3 text-sm">Preparing your files…</p>
                @error('uploads')<p role="alert" class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
                <x-lucid.action-feedback :message="$fileNotice" :revision="$fileNoticeRevision" tone="success" data-document-file-feedback />
            </section>
            @if ($workspace !== null)
            <details wire:ignore.self class="showroom-document-settings">
                <summary><span>Answer settings</span><x-lucid.icon name="chevron" /></summary>
                <div class="showroom-document-option-body">
                    <h2 class="lucid-content-heading">Choose how answers are written</h2>
                    <p class="text-sm">Optional: change the tone or amount of detail, then select Save settings. Your changes apply to future answers. Instructions can guide wording, but cannot replace facts in your documents.</p>
                    <form wire:submit="saveControls" class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="tone" label="Tone">@foreach ($tones as $option)<flux:select.option wire:key="doc-tone-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>@endforeach</flux:select>
                        <flux:select wire:model="answerLength" label="Answer length">@foreach ($lengths as $option)<flux:select.option wire:key="doc-length-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>@endforeach</flux:select>
                        <div class="sm:col-span-2 space-y-4">
                            @foreach ($guidanceNotes as $note)<div wire:key="doc-guidance-{{ $note['key'] }}-{{ $loop->index }}" class="space-y-3"><flux:select wire:model="guidanceNotes.{{ $loop->index }}.category" label="Instruction type">@foreach ($categories as $option)<flux:select.option wire:key="doc-guidance-{{ $note['key'] }}-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>@endforeach</flux:select><flux:field>
                                <flux:label for="document-instruction-{{ $note['key'] }}">Your instruction</flux:label>
                                <div class="lucid-reply-editor" data-instruction-editor>
                                    <textarea id="document-instruction-{{ $note['key'] }}" wire:model="guidanceNotes.{{ $loop->index }}.body" rows="2" maxlength="800" class="lucid-reply-field" placeholder="For example, use short bullet points."></textarea>
                                    <div class="lucid-reply-editor-tools">
                                        <x-dictation-button target="guidanceNotes.{{ $loop->index }}.body" noun="instruction" variant="icon" />
                                    </div>
                                </div>
                                <flux:error name="guidanceNotes.{{ $loop->index }}.body" />
                            </flux:field><flux:button type="button" wire:click="removeGuidance('{{ $note['key'] }}')">Remove instruction</flux:button></div>@endforeach
                            <div class="flex flex-wrap gap-3">@if (count($guidanceNotes) < 5)<flux:button type="button" wire:click="addGuidance">Add instruction</flux:button>@endif<flux:button type="submit" variant="primary" class="showroom-ticket-primary">Save settings</flux:button></div>
                            <x-lucid.action-feedback :message="$settingsNotice" :revision="$settingsNoticeRevision" tone="success" data-document-settings-feedback />
                        </div>
                    </form>
                </div>
            </details>
            <details wire:ignore.self class="showroom-document-settings" data-document-privacy>
                <summary><span>Privacy &amp; access</span><x-lucid.icon name="chevron" /></summary>
                <div class="showroom-document-option-body">
                    <h2 class="lucid-content-heading">Your files and access</h2>
                    <p class="text-sm">This workspace expires on {{ $expiresOn }}. Anyone using this browser can reopen it until then.</p>
                    <section class="space-y-3"><h3 class="lucid-section-heading">Return to this workspace</h3>
                        @if ($returnCode)<p class="text-sm">Copy this code somewhere safe to reopen your files from another browser. Anyone with the code can open them until the workspace expires or is deleted.</p><x-lucid.copy-return-code :code="$returnCode" />
                        @else<p class="text-sm">The return code is no longer displayed. Use your saved copy to return from another browser. You can keep working here without it.</p>@endif
                    </section>
                    <section class="space-y-3"><h3 class="lucid-section-heading">How your information is handled</h3><p class="text-sm leading-relaxed">{{ $disclosure }}</p></section>
                    <section class="space-y-3"><h3 class="lucid-section-heading">Remove this workspace</h3><p class="text-sm">Delete workspace permanently removes its files and conversation here. Your return code will stop working.</p><flux:modal.trigger name="delete-document-workspace"><flux:button variant="danger" class="showroom-destructive-action">Delete workspace</flux:button></flux:modal.trigger></section>
                </div>
            </details>
            <flux:modal name="delete-document-workspace" class="max-w-lg"><div class="space-y-4"><flux:heading level="2" class="lucid-content-heading">Delete this workspace?</flux:heading><p>This permanently removes its files and conversation here. Your return code will stop working. This cannot be undone.</p><div class="flex flex-wrap justify-end gap-3"><flux:modal.close><flux:button>Cancel</flux:button></flux:modal.close><flux:button wire:click="deleteWorkspace" variant="danger" class="showroom-destructive-action">Delete workspace permanently</flux:button></div></div></flux:modal>
            @else
            <details wire:ignore.self class="showroom-document-return"><summary>Open a previous workspace</summary><form wire:submit="openWithCode" class="mt-3 flex flex-wrap items-end gap-3"><flux:input wire:model="returnInput" label="Saved return code" autocomplete="off" /><flux:button type="submit">Open workspace</flux:button></form><flux:error name="returnInput" /></details>
            @endif
        </div>
        @if ($workspace !== null)
        @include('livewire.pages.workspace-preview-chat')
    @endif
</div>
