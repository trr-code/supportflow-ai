<div class="space-y-8">
    <x-demo-banner />

    <section class="space-y-4">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">Advanced safety tests</p>
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
            Try refusals, missing knowledge, and Agent examples
        </h1>
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600">
            These optional tests show how the assistant handles unsafe requests, missing information, and situations requiring human review.
        </p>
    </section>

    <div class="space-y-4">
        @foreach ($tests as $key => $test)
            <article wire:key="safety-test-{{ $key }}" class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
                <h2 class="text-base font-semibold text-harbor-ink">{{ $test['title'] }}</h2>
                <dl class="mt-3 space-y-3 text-sm leading-relaxed text-zinc-700">
                    <div>
                        <dt class="font-medium text-harbor-ink">What it tests</dt>
                        <dd class="mt-1">{{ $test['what'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-harbor-ink">What to do</dt>
                        <dd class="mt-1">{{ $test['action'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-harbor-ink">Expected result</dt>
                        <dd class="mt-1">{{ $test['expect'] }}</dd>
                    </div>
                </dl>
                @if ($test['promptKey'] !== null && $test['button'] !== null)
                    <div class="mt-4">
                        <flux:button type="button" variant="filled" wire:click="fillChat('{{ $test['promptKey'] }}')">
                            {{ $test['button'] }}
                        </flux:button>
                    </div>
                @elseif (($test['enterAgentNext'] ?? null) !== null && $test['button'] !== null)
                    <div class="mt-4">
                        @auth
                            <flux:button variant="filled" :href="route('agent.tickets.index', ['scenarios' => 1]).'#agent-scenarios'" wire:navigate>
                                {{ $test['button'] }}
                            </flux:button>
                        @else
                            <form method="POST" action="{{ route('demo.enter-agent') }}">
                                @csrf
                                <input type="hidden" name="next" value="{{ $test['enterAgentNext'] }}">
                                <flux:button type="submit" variant="filled">{{ $test['button'] }}</flux:button>
                            </form>
                        @endauth
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</div>
