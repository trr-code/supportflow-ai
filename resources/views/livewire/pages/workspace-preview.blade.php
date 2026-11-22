<div @class (['showroom-document-component' => $embedded])>
    @if ($embedded)
        @include ('livewire.pages.workspace-documents-embedded')
    @else
        <div class="space-y-8" @if ($indexing) wire:poll.3s @endif>
            <section class="space-y-3">
                <p class="text-xs font-semibold tracking-[0.2em] text-harbor-pine uppercase">Private preview</p>
                <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
                    Your documents, for up to 7 days
                </h1>
                <p class="max-w-2xl text-base leading-relaxed text-zinc-600">Upload files, set how answers sound, and chat against only that set. The Harbor Outfitters demo stays separate. No account needed. The workspace expires seven days after it is created, then it is cleaned up automatically. Delete workspace removes it immediately.</p>
            </section>

            <p class="max-w-3xl rounded-2xl border border-harbor-sand-deep bg-white p-5 text-sm leading-relaxed text-zinc-700">
                {{ $disclosure }}
            </p>

            @if ($workspace === null)
                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                        <h2 class="lucid-section-heading text-harbor-ink">Start now</h2>
                        <p class="text-sm leading-relaxed text-zinc-600">This browser can reopen the workspace until it expires. Anyone using this browser can reopen it during that time.</p>
                        <flux:button type="button" variant="primary" wire:click="start"
                            >Start a private preview</flux:button
                        >
                    </div>

                    <form
                        wire:submit="openWithCode"
                        class="space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5"
                    >
                        <h2 class="lucid-section-heading text-harbor-ink">Open with a return code</h2>
                        <flux:input wire:model="returnInput" label="Return code" autocomplete="off" />
                        <flux:button type="submit" class="lucid-outline-action">Open workspace</flux:button>
                    </form>
                </section>
            @else
                @if ($returnCode)
                    <section class="space-y-3 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                        <h2 class="lucid-section-heading text-harbor-ink">Save your return code</h2>
                        <p class="font-mono text-lg tracking-wide text-harbor-ink" data-return-code>{{ $returnCode }}</p>
                        <button
                            type="button"
                            class="text-sm font-medium text-harbor-pine underline-offset-2 hover:underline"
                            x-data
                            x-on:click="navigator.clipboard.writeText(@js($returnCode)).then(() => { $flux.toast({ text: 'Copied.', variant: 'success', duration: 3000 }) })"
                        >
                            Copy return code
                        </button>
                        <ul class="list-disc space-y-1.5 ps-5 text-sm leading-relaxed text-zinc-700">
                            <li>
                                This browser can reopen this workspace until
                                <x-lucid.local-time :value="$workspace?->expires_at" :date-only="true" />. Anyone using
                                this browser can reopen it during that time.
                            </li>
                            <li>
                                Save the return code to open it from another browser, or on this browser after you leave
                                or clear cookies.
                            </li>
                            <li>
                                Anyone with the return code can open the workspace until it expires or you delete it.
                            </li>
                            <li>The code is not stored. A hash is.</li>
                        </ul>
                        <flux:button type="button" wire:click="dismissReturnCode" class="lucid-outline-action"
                            >I've saved this code</flux:button
                        >
                    </section>
                @endif

                <p class="text-sm text-zinc-600">This workspace expires on <x-lucid.local-time :value="$workspace?->expires_at" :date-only="true" />.</p>

                <div class="grid items-start gap-6">
                    <div class="space-y-6">
                        <section class="space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                            <h2 class="lucid-section-heading text-harbor-ink">Documents</h2>
                            <form wire:submit="storeDocuments" class="space-y-3">
                                <label
                                    class="relative inline-flex cursor-pointer items-center justify-center rounded-lg border border-harbor-sand-deep bg-white px-3 py-2 text-sm font-medium text-zinc-800 shadow-xs hover:bg-zinc-50"
                                >
                                    Choose files
                                    <input
                                        type="file"
                                        wire:model="uploads"
                                        multiple
                                        accept=".pdf,.docx,.txt,.md,.markdown,application/pdf,text/plain,text/markdown"
                                        class="absolute inset-0 cursor-pointer opacity-0"
                                    />
                                </label>
                                <p class="text-sm text-zinc-600">PDF, DOCX, TXT, and Markdown. Up to {{ (int) (config('supportflow.workspaces.max_file_bytes') / 1048576) }} MB each, {{ (int) config('supportflow.workspaces.max_batch') }} files at a time, and {{ (int) config('supportflow.workspaces.max_documents') }} documents in this workspace. PDFs need text you can highlight and copy. Image-only scans won’t work.</p>
                                <p wire:loading wire:target="uploads" class="text-sm text-zinc-600">Uploading…</p>
                                @if ($uploads !== [])
                                    <ul class="space-y-1 text-sm text-harbor-ink">
                                        @foreach ($uploads as $upload)
                                            <li wire:key="workspace-upload-{{ $upload->getFilename() }}">
                                                {{ $upload->getClientOriginalName() }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @error ('uploads')
                                    <p class="text-sm text-red-700">{{ $message }}</p>
                                @enderror
                                <flux:button type="submit" class="lucid-outline-action">Upload</flux:button>
                            </form>
                            <ul class="space-y-3">
                                @forelse ($documents as $document)
                                    <li
                                        wire:key="workspace-document-{{ $document->id }}"
                                        class="rounded-xl border border-harbor-sand-deep p-3 text-sm"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="font-medium text-harbor-ink">{{ $document->original_name }}</p>
                                            <button
                                                type="button"
                                                class="pine-hover text-harbor-pine underline underline-offset-2"
                                                wire:click="removeDocument('{{ $document->id }}')"
                                                wire:confirm="Delete this document now. The file and its answers are removed."
                                            >
                                                Delete
                                            </button>
                                        </div>
                                        @if ($document->status === \App\Enums\WorkspaceDocumentStatus::Ready)
                                            <p class="mt-1 text-zinc-600">Ready for answers</p>
                                        @elseif ($document->status === \App\Enums\WorkspaceDocumentStatus::Failed)
                                            <p class="mt-1 text-red-700">Could not read document</p>
                                            <p class="mt-1 text-zinc-600">{{
                                                \App\Support\WorkspaceCopy::fileFailureMessage(
                                                    $document->failure_reason,
                                                )
                                            }}</p>
                                        @else
                                            <p class="mt-1 text-zinc-600">Reading this document…</p>
                                        @endif
                                    </li>
                                @empty
                                    <li class="text-sm text-zinc-600">No documents yet.</li>
                                @endforelse
                            </ul>
                        </section>

                        <form
                            wire:submit="saveControls"
                            class="space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5"
                        >
                            <h2 class="lucid-section-heading text-harbor-ink">How answers sound</h2>
                            <p class="text-sm leading-relaxed text-zinc-600">Draft changes apply to the next question in this preview right away. Save settings keeps them for a later visit. A retrieved passage still wins.</p>
                            <flux:select wire:model="tone" label="Tone">
                                @foreach ($tones as $option)
                                    <flux:select.option
                                        wire:key="workspace-tone-{{ $option->value }}"
                                        value="{{ $option->value }}"
                                    >
                                        {{ $option->label() }}</flux:select.option
                                    >
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="answerLength" label="Length">
                                @foreach ($lengths as $option)
                                    <flux:select.option
                                        wire:key="workspace-length-{{ $option->value }}"
                                        value="{{ $option->value }}"
                                    >
                                        {{ $option->label() }}</flux:select.option
                                    >
                                @endforeach
                            </flux:select>
                            <div class="space-y-3">
                                @foreach ($guidanceNotes as $note)
                                    <div
                                        wire:key="workspace-guidance-{{ $note['key'] }}"
                                        class="space-y-2 rounded-xl border border-harbor-sand-deep p-3"
                                    >
                                        <flux:select
                                            wire:model="guidanceNotes.{{ $loop->index }}.category"
                                            label="Guidance"
                                        >
                                            @foreach ($categories as $option)
                                                <flux:select.option
                                                    wire:key="workspace-guidance-{{ $note['key'] }}-{{ $option->value }}"
                                                    value="{{ $option->value }}"
                                                >
                                                    {{ $option->label() }}</flux:select.option
                                                >
                                            @endforeach
                                        </flux:select>
                                        <flux:textarea
                                            wire:model="guidanceNotes.{{ $loop->index }}.body"
                                            rows="3"
                                            placeholder="Voice or handoff. This cannot add facts."
                                        />
                                        <button
                                            type="button"
                                            class="pine-hover text-sm text-harbor-pine underline underline-offset-2"
                                            wire:click.preserve-scroll="removeGuidance('{{ $note['key'] }}')"
                                        >
                                            Remove note
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            @if (count($guidanceNotes) < 5)
                                <flux:button
                                    type="button"
                                    wire:click.preserve-scroll="addGuidance"
                                    class="lucid-outline-action"
                                    >Add guidance</flux:button
                                >
                            @endif
                            <flux:button type="submit" class="lucid-outline-action">Save settings</flux:button>
                        </form>

                        <section class="space-y-3 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                            <h2 class="lucid-section-heading text-harbor-ink">Leave or delete</h2>
                            <div class="space-y-2">
                                <flux:button type="button" wire:click="leave" class="lucid-outline-action"
                                    >Leave this workspace</flux:button
                                >
                                <p class="text-sm text-zinc-600">{{ $leaveCopy }}</p>
                            </div>
                            <div class="space-y-2">
                                <flux:button
                                    type="button"
                                    variant="danger"
                                    wire:click="deleteWorkspace"
                                    wire:confirm="{{ $deleteCopy }}"
                                >
                                    Delete workspace
                                </flux:button>
                                <p class="text-sm text-zinc-600">{{ $deleteCopy }}</p>
                            </div>
                        </section>
                    </div>

                    @include ('livewire.pages.workspace-preview-chat')
                </div>
            @endif
        </div>

    @endif
</div>
