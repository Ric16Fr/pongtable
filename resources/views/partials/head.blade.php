<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' · '.config('app.name', 'pongtable') : config('app.name', 'pongtable') }}
</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">

<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|jetbrains-mono:400,500,700&display=swap">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
