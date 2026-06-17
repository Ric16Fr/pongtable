<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' · '.config('app.name', 'pongtable') : config('app.name', 'pongtable') }}
</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Default appearance: dark (broadcast canvas). Honors any user choice
     already stored by @fluxAppearance; only seeds 'dark' on first visit. --}}
<script>
    if (!window.localStorage.getItem('flux.appearance')) {
        window.localStorage.setItem('flux.appearance', 'dark');
    }
</script>
@fluxAppearance
