@props(['area' => 'demos', 'active' => 'harbor'])
<aside {{ $attributes->class('showroom-sidebar showroom-dark') }} id="demo-navigation">
    <div class="showroom-sidebar-brand"><a href="{{ route('home') }}" wire:navigate aria-label="Lucid home"><x-lucid.wordmark /></a><button type="button" x-on:click="collapsed = !collapsed" :aria-expanded="!collapsed" aria-controls="demo-navigation-links" aria-label="Toggle demo navigation" class="showroom-collapse"><x-lucid.icon name="collapse" /></button></div>
    <nav id="demo-navigation-links" aria-label="{{ $area === 'agent' ? 'Agent desk' : 'Demo navigation' }}">
        <a href="{{ route('home') }}" wire:navigate class="showroom-nav-item" title="Home" aria-label="Home"><x-lucid.icon name="home" /><span>Home</span></a>
        @if ($area === 'agent')
            <p class="showroom-nav-label">Agent desk</p>
            <a href="{{ route('dashboard') }}" wire:navigate @class(['showroom-nav-item', 'is-active' => request()->routeIs('dashboard')]) @if(request()->routeIs('dashboard')) aria-current="page" @endif title="Dashboard" aria-label="Dashboard"><x-lucid.icon name="home" /><span>Dashboard</span></a>
            <a href="{{ route('agent.tickets.index') }}" wire:navigate @class(['showroom-nav-item', 'is-active' => request()->routeIs('agent.*')]) @if(request()->routeIs('agent.*')) aria-current="page" @endif title="Tickets" aria-label="Tickets"><x-lucid.icon name="chat" /><span>Tickets</span></a>
            <a href="{{ route('demo.harbor') }}" wire:navigate class="showroom-nav-item" title="Customer support" aria-label="Customer support"><x-lucid.icon name="people" /><span>Customer support</span></a>
        @else
            <p class="showroom-nav-label">Demos</p>
            @foreach (['harbor' => ['Customer support', 'chat'], 'documents' => ['Document chat', 'document'], 'invoices' => ['Invoice review', 'invoice-dollar']] as $key => $item)
                <a href="{{ route('demo.'.$key) }}" wire:navigate wire:key="sidebar-demo-{{ $key }}" @class(['showroom-nav-item', 'is-active' => $active === $key]) @if($active === $key) aria-current="page" @endif title="{{ $item[0] }}" aria-label="{{ $item[0] }}"><x-lucid.icon :name="$item[1]" /><span>{{ $item[0] }}</span></a>
            @endforeach
        @endif
        <p class="showroom-nav-label">Support</p>
        <a href="{{ route('knowledge.index', \App\Support\PolicyNavigation::forCurrentPage()) }}" wire:navigate @class(['showroom-nav-item', 'is-active' => request()->routeIs('knowledge.*')]) @if(request()->routeIs('knowledge.*')) aria-current="page" @endif title="Browse policies" aria-label="Browse policies"><x-lucid.icon name="shield" /><span>Browse policies</span></a>
        @if ($area !== 'agent')
            @auth
                <a href="{{ route('dashboard') }}" target="_blank" rel="noopener" class="showroom-nav-item" title="Agent desk (opens in another tab or window)" aria-label="Agent desk (opens in another tab or window)"><x-lucid.icon name="people" /><span>Agent desk</span></a>
            @else
                <form method="POST" action="{{ route('demo.enter-agent') }}" target="_blank" rel="noopener">@csrf<button type="submit" class="showroom-nav-item w-full text-left" title="Agent desk (opens in another tab or window)" aria-label="Agent desk (opens in another tab or window)"><x-lucid.icon name="people" /><span>Agent desk</span></button></form>
            @endauth
        @endif
    </nav>
    <img src="{{ asset('images/lucid-waves.png') }}" alt="" width="1536" height="1024" class="showroom-sidebar-waves">
    <a href="{{ route('demo.environment') }}" wire:navigate class="showroom-nav-item showroom-help" title="Help" aria-label="Help"><x-lucid.icon name="help" /><span>Help</span></a>
</aside>
