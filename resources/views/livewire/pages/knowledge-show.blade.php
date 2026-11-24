<x-lucid.workspace-page instruction-title="Read a policy">
    <div class="showroom-policy-article space-y-6">
        <x-lucid.page-header
            :title="$article->title"
            :description="$article->category->label() . ' policy for Harbor.'"
            heading-id="policy-heading"
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-4">
                    @if ($returnUrl)
                        <a href="{{ $returnUrl }}" wire:navigate class="policy-link" data-policy-return
                            ><x-lucid.icon name="arrow" class="lucid-return-arrow" /><span>{{ $returnLabel }}</span></a
                        >
                    @endif
                    <a
                        href="{{ route('knowledge.index', ['topic' => $topic, ...$origin]) }}"
                        wire:navigate
                        class="policy-link"
                        >{{
                            $topic === 'all'
                                ? 'Back to all policies'
                                : 'Back to policies'
                        }}</a
                    >
                </div>
            </x-slot:actions>
        </x-lucid.page-header>

        <div class="border-t border-[#111] pt-4" data-policy-content>
            <article
                class="policy-prose [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-harbor-ink [&_p]:mt-3 [&_p]:leading-relaxed [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:ps-5 text-zinc-800"
            >
                {!! $article->bodyHtml() !!}
            </article>
        </div>
    </div>
    <x-slot:instructions>
        <p>Follow these steps to inspect the policy and compare it with an answer.</p>
        <ol class="showroom-support-steps showroom-ticket-steps">
            <li>
                <span>1</span>
                <div>
                    <h3>Read the policy text</h3>
                    <p>The middle panel shows the published policy. Its headings separate the rules for each topic.</p>
                </div>
            </li>
            <li>
                <span>2</span>
                <div>
                    <h3>Check the answer against it</h3>
                    <p>
                        Compare the reply with the published rules, including any conditions or exceptions.
                        @if ($returnUrl)
                            Choose
                            <strong>{{ $returnLabel }}</strong>
                            above to continue your work.
                        @endif
                    </p>
                </div>
            </li>
            <li>
                <span>3</span>
                <div>
                    <h3>Explore another topic</h3>
                    <p><strong>{{
                        $topic === 'all'
                            ? 'Back to all policies'
                            : 'Back to policies'
                    }}</strong> returns to the list and keeps your selected category. Opening or reading a policy does not send a reply.</p>
                </div>
            </li>
        </ol>
    </x-slot:instructions>
</x-lucid.workspace-page>
