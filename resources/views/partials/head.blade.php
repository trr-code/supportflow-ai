<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
@if (request()->routeIs(
        'agent.*',
        'dashboard',
        'tickets.status',
        'tickets.create',
        'workspaces.preview',
        'documents.invoices',
        'profile.edit'
    ))
    <meta name="robots" content="noindex, nofollow" />
@endif
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    @php
        $pageTitle = filled($title ?? null) ? trim((string) $title) : '';
        $appName = 'Lucid';
    @endphp
    {{
        $metaTitle ??
            ($pageTitle === '' || strcasecmp($pageTitle, $appName) === 0
                ? $appName
                : $pageTitle . ' - ' . $appName)
    }}
</title>

@isset ($description)
    <meta name="description" content="{{ $description }}" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="Lucid" />
    <meta property="og:title" content="{{ $metaTitle ?? ($pageTitle === '' ? 'Lucid' : $pageTitle.' - Lucid') }}" />
    <meta property="og:description" content="{{ $description }}" />
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="{{ $metaTitle ?? ($pageTitle === '' ? 'Lucid' : $pageTitle.' - Lucid') }}" />
    <meta name="twitter:description" content="{{ $description }}" />
@endisset
@isset ($canonical)
    <link rel="canonical" href="{{ $canonical }}" />
    <meta property="og:url" content="{{ $canonical }}" />
@endisset

<link rel="icon" href="/favicon.ico" sizes="any" />
<link rel="icon" href="/favicon.svg" type="image/svg+xml" />
<link rel="apple-touch-icon" href="/apple-touch-icon.png" />

@fonts

@vite (['resources/css/app.css', 'resources/js/app.js'])
<style>
    :root {
        color-scheme: only light;
    }
</style>
