<div class="space-y-6" @if ($this->shouldPoll()) wire:poll.5s.visible @endif>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-zinc-500">{{ $ticket->reference }}
                @if ($ticket->source === \App\Enums\TicketSource::VisitorDemo)
                    · Live demo submission
                @endif
            </p>
            <h1 class="text-2xl font-semibold">{{ $ticket->subject }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ $ticket->customer_name }} · {{ $ticket->customer_email }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-status-badge :status="$ticket->status" />
            @if ($ticket->status === \App\Enums\TicketStatus::AiFailed)
                <flux:button size="sm" wire:click="retryAi">Retry AI</flux:button>
            @endif
        </div>
    </div>

    @if ($flash)
        <div class="rounded-md bg-harbor-ocean/10 p-3 text-sm text-harbor-ocean" role="status">{{ $flash }}</div>
    @endif

    @if ($ticket->status === \App\Enums\TicketStatus::Submitted || $ticket->status === \App\Enums\TicketStatus::Triaging)
        <p class="text-sm text-zinc-600">AI is still reviewing this ticket.</p>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <h2 class="font-medium">Customer request</h2>
                <p class="mt-1 text-sm text-zinc-500">Product: {{ $ticket->product ?: '—' }}</p>
                <p class="mt-3 whitespace-pre-wrap text-sm">{{ $ticket->description }}</p>
            </section>

            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <h2 class="font-medium">Triage</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    <span class="font-medium text-harbor-ink">AI classification confidence:</span>
                    {{ $ticket->classificationConfidenceLevel()->label() }}{{ $ticket->classification_confidence !== null ? ' ('.number_format($ticket->classification_confidence * 100, 0).'%)' : '' }}—model-estimated, not a retrieval score.
                </p>
                <p class="mt-1 text-sm text-zinc-500">
                    <span class="font-medium text-harbor-ink">Knowledge match:</span>
                    {{ $ticket->knowledgeMatchLevel()->label() }}{{ $ticket->retrieval_similarity !== null ? ' (similarity '.number_format($ticket->retrieval_similarity, 2).')' : '' }}—measured from the knowledge base. This gates suggested drafts.
                </p>

                @if ($ticket->ai_summary)
                    <p class="mt-3 text-sm">{{ $ticket->ai_summary }}</p>
                @endif

                @if ($ticket->decision_factors)
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($ticket->decision_factors as $factor)
                            <li wire:key="factor-{{ $ticket->id }}-{{ hash('sha256', $factor) }}">{{ $factor }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($ticket->injection_suspected)
                    <p class="mt-3 rounded-md bg-harbor-coral/10 p-2 text-sm text-red-950">Prompt-injection suspected. Draft skipped; a human should reply.</p>
                @endif

                <form wire:submit="saveOverrides" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm">
                        Category
                        <select wire:model="category" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2">
                            <option value="">—</option>
                            @foreach (\App\Enums\TicketCategory::cases() as $case)
                                <option wire:key="cat-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        Priority
                        <select wire:model="priority" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2">
                            <option value="">—</option>
                            @foreach (\App\Enums\TicketPriority::cases() as $case)
                                <option wire:key="pri-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        Sentiment
                        <select wire:model="sentiment" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2">
                            <option value="">—</option>
                            @foreach (\App\Enums\TicketSentiment::cases() as $case)
                                <option wire:key="sen-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        Department
                        <select wire:model="department" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2">
                            <option value="">—</option>
                            @foreach (\App\Enums\TicketDepartment::cases() as $case)
                                <option wire:key="dep-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="sm:col-span-2">
                        <flux:button type="submit" size="sm">Save override</flux:button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <h2 class="font-medium">Suggested reply</h2>
                @if ($pendingReply)
                    <p class="mt-1 text-sm text-zinc-500">{{ $replyPanel->message }} Grounded: {{ $pendingReply->grounded ? 'yes' : 'no' }}.</p>
                    <flux:textarea wire:model="draftBody" rows="8" class="mt-3" />
                    <div class="mt-3 flex flex-wrap gap-2">
                        <flux:button variant="primary" wire:click="approveAndSend">Approve and send</flux:button>
                        <flux:button wire:click="saveDraft">Save edits</flux:button>
                        <flux:button variant="ghost" wire:click="reject">Reject</flux:button>
                        <flux:button variant="ghost" wire:click="regenerate">Regenerate</flux:button>
                    </div>
                    @if ($citedSourceGroups !== [])
                        <h3 class="mt-4 text-sm font-medium">Sources</h3>
                        <ul class="mt-2 space-y-2">
                            @foreach ($citedSourceGroups as $group)
                                <li wire:key="source-article-{{ $group['article_id'] }}" class="rounded-md border border-harbor-sand-deep p-3 text-sm">
                                    <p class="font-medium">{{ $group['title'] }}</p>
                                    @foreach ($group['chunks'] as $chunk)
                                        <div wire:key="source-chunk-{{ $chunk->id }}" class="mt-2">
                                            @if ($chunk->heading)
                                                <p class="text-zinc-500">{{ $chunk->heading }}</p>
                                            @endif
                                            <p class="mt-1 text-zinc-700">{{ \Illuminate\Support\Str::limit($chunk->body, 180) }}</p>
                                        </div>
                                    @endforeach
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="mt-2 text-sm text-zinc-600">{{ $replyPanel->message }}</p>
                @endif
            </section>

            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <h2 class="font-medium">Human reply</h2>
                <flux:textarea wire:model="customReply" rows="4" label="Write a reply without AI" />
                <div class="mt-3">
                    <flux:button wire:click="sendCustom">Simulate send</flux:button>
                </div>
            </section>

            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <h2 class="font-medium">Public thread</h2>
                @forelse ($publicMessages as $message)
                    <article wire:key="public-{{ $message->id }}" class="mt-3 rounded-md bg-harbor-sand p-3 text-sm">
                        <p class="text-xs text-zinc-500">{{ $message->author_type->value }} · {{ $message->created_at?->toDayDateTimeString() }}</p>
                        <p class="mt-1 whitespace-pre-wrap">{{ $message->body }}</p>
                    </article>
                @empty
                    <p class="mt-2 text-sm text-zinc-500">No public messages yet.</p>
                @endforelse
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <h2 class="font-medium">Status</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (\App\Enums\TicketStatus::cases() as $case)
                        <button
                            type="button"
                            wire:key="set-status-{{ $case->value }}"
                            wire:click="changeStatus('{{ $case->value }}')"
                            class="rounded-full px-2 py-1 text-xs {{ $ticket->status === $case ? 'bg-harbor-pine text-white' : 'bg-harbor-sand text-harbor-ink ring-1 ring-inset ring-harbor-sand-deep' }}"
                        >{{ $case->label() }}</button>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <h2 class="font-medium">Internal notes</h2>
                <p class="text-xs text-zinc-500">Never shown on the customer status URL.</p>
                <flux:textarea wire:model="note" rows="3" class="mt-2" />
                <div class="mt-2">
                    <flux:button size="sm" wire:click="addNote">Add note</flux:button>
                </div>
                @foreach ($notes as $noteMessage)
                    <article wire:key="note-{{ $noteMessage->id }}" class="mt-3 rounded-md bg-harbor-sand p-2 text-sm text-harbor-ink">
                        {{ $noteMessage->body }}
                    </article>
                @endforeach
            </section>

            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <h2 class="font-medium">Timeline</h2>
                <ol class="mt-3 space-y-2 text-sm">
                    @foreach ($events as $event)
                        <li wire:key="event-{{ $event->id }}">
                            <span class="font-medium">{{ str($event->type->value)->replace('_', ' ')->title() }}</span>
                            <span class="text-zinc-500">· {{ $event->actor }} · {{ $event->created_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">AI runs</h2>
                    <flux:button size="sm" variant="ghost" wire:click="$toggle('showTechnical')">{{ $showTechnical ? 'Hide' : 'Technical' }}</flux:button>
                </div>
                @if ($showTechnical)
                    <p class="mt-2 text-xs text-zinc-500">{{ config('supportflow.pricing.disclaimer') }} Rates as of {{ config('supportflow.pricing.as_of') }}. Missing usage shows as —.</p>
                    @foreach ($runs as $run)
                        <article wire:key="run-{{ $run->id }}" class="mt-3 rounded-md border border-harbor-sand-deep p-3 text-xs">
                            <p class="font-medium">{{ $run->feature->value }} · {{ $run->status->value }} · {{ $run->model }}</p>
                            <p>Tokens in/out: {{ $run->input_tokens ?? '—' }}/{{ $run->output_tokens ?? '—' }}</p>
                            <p>Estimated cost: {{ $run->estimated_cost !== null ? '$'.number_format($run->estimated_cost, 6) : '—' }}</p>
                            @if (is_array($run->payload) && isset($run->payload['retrieval_similarity']))
                                <p>Raw similarity: {{ $run->payload['retrieval_similarity'] }}</p>
                            @endif
                            @if ($ticket->retrieval_similarity !== null)
                                <p>Measured retrieval similarity: {{ $ticket->retrieval_similarity }}</p>
                            @endif
                            @if ($run->error)
                                <p class="text-red-700">{{ $run->error }}</p>
                            @endif
                        </article>
                    @endforeach
                @endif
            </section>
        </div>
    </div>
</div>
