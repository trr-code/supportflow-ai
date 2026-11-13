@props(['title', 'badge' => null, 'agent' => false, 'dashboardGuide' => false, 'queueGuide' => false, 'pageGuide' => false])
@php($hasInstructions = $dashboardGuide || $queueGuide || $pageGuide)
<div {{ $attributes->class(['showroom-workspace showroom-secondary showroom-support-unified', 'showroom-agent' => $agent, 'showroom-dashboard-frame' => $dashboardGuide, 'showroom-instructions-frame' => $hasInstructions, 'showroom-queue-frame' => $queueGuide, 'showroom-page-managed' => $pageGuide]) }} x-data="{ collapsed: false, mobileNav: false, instructionsOpen: true }" :class="{ 'sidebar-collapsed': collapsed, 'mobile-nav-open': mobileNav }">
    <x-lucid.sidebar :area="$agent ? 'agent' : 'support'" active="" />
    <div class="showroom-secondary-area" @if ($hasInstructions) :class="{ 'guide-hidden': !instructionsOpen }" @endif>
        <header class="showroom-toolbar showroom-dark">
            <button type="button" class="showroom-mobile-toggle" x-on:click="mobileNav = !mobileNav" :aria-expanded="mobileNav" aria-controls="demo-navigation" aria-label="Toggle demo navigation"><x-lucid.icon name="collapse" /></button>
            <p>{{ $title }}</p><span class="showroom-demo-badge">{{ $badge ?? ($agent ? 'Agent view' : 'Customer view') }}</span>
            <div class="ms-auto flex flex-wrap items-center gap-3">
                @if ($hasInstructions)<button type="button" x-show="!instructionsOpen" x-cloak x-on:click="instructionsOpen = true; $nextTick(() => document.getElementById('demo-guide-heading').focus())" aria-controls="demo-guide" :aria-expanded="instructionsOpen" class="showroom-outline-light"><x-lucid.icon name="help" /><span>Show instructions</span></button>@endif
                @unless (request()->routeIs('demo.environment'))
                    <a href="{{ route('demo.harbor') }}" wire:navigate class="showroom-outline-light">Customer chat</a>
                @endunless
                @if ($agent)
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="showroom-outline-light">Leave</button></form>
                @endif
            </div>
        </header>
        <main id="main-content" class="showroom-secondary-content">{{ $slot }}</main>
        @if ($dashboardGuide)
            <x-lucid.instruction-panel title="Explore the dashboard" :client-controlled="true" :scroll-controls="true" x-show="instructionsOpen" x-cloak>
                <p>This is the agent view. Open Tickets to find the customer request. Switch back to the original tab or window to see what the customer sees.</p>
                <ol class="showroom-support-steps showroom-ticket-steps">
                    <li><span>1</span><div><h3>Check the ticket totals</h3><p>The four boxes at the top show unresolved tickets, replies awaiting review, requests needing human help, and urgent requests.</p></div></li>
                    <li><span>2</span><div><h3>Find work in Needs attention</h3><p>Select Review drafts to find replies ready to check, View escalations for requests needing human help, or View tickets to find failed AI attempts.</p></div></li>
                    <li><span>3</span><div><h3>Understand status and priority</h3><p>By status shows where tickets are in the process. By priority shows how urgently open tickets need attention. Unassigned means no priority has been set.</p></div></li>
                    <li><span>4</span><div><h3>See how many tickets visitors submitted</h3><p>Live demo submissions shows how many tickets people have submitted while trying this demo.</p></div></li>
                    <li><span>5</span><div><h3>See how many AI replies people sent</h3><p>AI-supported sends shows how many policy-based AI replies a person approved and sent. Replies written entirely by a person are not included.</p></div></li>
                </ol>
            </x-lucid.instruction-panel>
        @endif
    </div>
</div>
