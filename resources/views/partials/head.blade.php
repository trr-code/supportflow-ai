<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>
    @php
        $pageTitle = filled($title ?? null) ? trim((string) $title) : '';
        $appName = (string) config('app.name', 'Laravel');
    @endphp
    {{ ($pageTitle === '' || strcasecmp($pageTitle, $appName) === 0) ? $appName : $pageTitle.' - '.$appName }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    :root {
        color-scheme: only light;
    }
</style>
