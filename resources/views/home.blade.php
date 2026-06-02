<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stage-bg text-stage-text antialiased">
        @php
            $tournament = \App\Models\Tournament::query()->latest()->first();
            $liveMatch = $tournament
                ? \App\Models\GameMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->whereIn('status', ['active', 'pre_entry', 'scoring'])
                    ->with(['homeTeam', 'awayTeam', 'table'])
                    ->first()
                : null;
            $phaseLabels = [
                'setup' => 'Vorbereitung',
                'group' => 'Gruppenphase',
                'ko' => 'KO-Phase',
                'finished' => 'Beendet',
            ];
            $phase = $tournament ? ($phaseLabels[$tournament->status] ?? null) : null;
            $teamCount = $tournament?->teams()->count() ?? 0;
            $tableCount = $tournament?->tables()->count() ?? 0;
            $matchCount = $tournament?->matches()->count() ?? 0;
            $finishedCount = $tournament?->matches()->where('status', 'finished')->count() ?? 0;
        @endphp

        <div class="relative flex min-h-screen flex-col">
            <header class="border-b border-stage-line">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5 lg:px-10">
                    <a href="{{ route('home') }}" class="wordmark text-xl">pongtable</a>
                    <nav class="flex items-center gap-2 text-sm">
                        <x-appearance-toggle />
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="rounded-md px-4 py-2 font-medium text-stage-text hover:bg-stage-surface transition">
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="rounded-md px-4 py-2 font-medium text-stage-text-muted hover:text-stage-text hover:bg-stage-surface transition">
                                {{ __('Schiri-Login') }}
                            </a>
                        @endauth
                    </nav>
                </div>
            </header>

            <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-16 px-6 pt-16 pb-12 lg:px-10 lg:pt-24">

                {{-- Hero: broadcast-grade title moment --}}
                <section class="flex flex-col gap-8">
                    @if ($tournament)
                        <div class="font-label flex items-center gap-3 text-trophy-gold">
                            <span class="block h-px w-12 bg-trophy-gold"></span>
                            <span>{{ $phase }}</span>
                            <span class="text-stage-text-dim">·</span>
                            <span class="text-stage-text-muted">Tournament Live</span>
                        </div>

                        <h1 class="font-display text-[clamp(3rem,9vw,7.5rem)] text-stage-text">
                            {{ $tournament->name }}
                        </h1>

                        <p class="max-w-xl text-base leading-relaxed text-stage-text-muted lg:text-lg">
                            Mehrere Tische parallel, automatisch verteilte Gruppen, KO-Bracket mit Live-Timer. Das Publikum schaut zu, die Schiedsrichter zählen, der Algorithmus rechnet sauber durch.
                        </p>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <a href="{{ route('tournament.public', $tournament->public_token) }}"
                               class="inline-flex items-center gap-2 rounded-md bg-stage-text px-6 py-3 text-base font-semibold text-stage-bg hover:opacity-90 transition">
                                Zum Turnier
                                <svg viewBox="0 0 16 16" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4l4 4-4 4"/></svg>
                            </a>
                            @auth
                                <a href="{{ route('matches.index') }}"
                                   class="inline-flex items-center gap-2 rounded-md border border-stage-line-strong px-6 py-3 text-base font-medium text-stage-text hover:bg-stage-surface transition">
                                    Match-Verwaltung
                                </a>
                            @endauth
                        </div>

                        {{-- Inline typographic stats. Not a card grid. --}}
                        <dl class="mt-6 flex flex-wrap items-baseline gap-x-10 gap-y-3 text-sm">
                            <div class="flex items-baseline gap-2">
                                <dt class="font-label text-stage-text-dim">Teams</dt>
                                <dd class="font-numeric text-xl font-semibold text-stage-text">{{ $teamCount }}</dd>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <dt class="font-label text-stage-text-dim">Tische</dt>
                                <dd class="font-numeric text-xl font-semibold text-stage-text">{{ $tableCount }}</dd>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <dt class="font-label text-stage-text-dim">Matches</dt>
                                <dd class="font-numeric text-xl font-semibold text-stage-text">{{ $finishedCount }}<span class="text-stage-text-dim"> / {{ $matchCount }}</span></dd>
                            </div>
                        </dl>
                    @else
                        <div class="font-label text-stage-text-dim">Kein Turnier aktiv</div>
                        <h1 class="font-display text-[clamp(3rem,9vw,7.5rem)] text-stage-text">
                            pongtable
                        </h1>
                        <p class="max-w-xl text-base leading-relaxed text-stage-text-muted lg:text-lg">
                            Selbst gehostete Bierpong-Turnierverwaltung. Gruppen, KO-Bracket, Live-Timer und eine Bracket-Ansicht für die Großleinwand.
                        </p>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            @auth
                                <a href="{{ route('tournament.setup') }}"
                                   class="inline-flex items-center gap-2 rounded-md bg-stage-text px-6 py-3 text-base font-semibold text-stage-bg hover:opacity-90 transition">
                                    Turnier anlegen
                                </a>
                            @else
                                <a href="{{ route('login') }}"
                                   class="inline-flex items-center gap-2 rounded-md bg-stage-text px-6 py-3 text-base font-semibold text-stage-bg hover:opacity-90 transition">
                                    Schiri-Login
                                </a>
                            @endauth
                        </div>
                    @endif
                </section>

                {{-- Live face-off teaser. Only renders when something is in play. --}}
                @if ($liveMatch)
                    @php
                        $home = $liveMatch->homeTeam;
                        $away = $liveMatch->awayTeam;
                    @endphp
                    <section class="relative overflow-hidden rounded-lg border border-stage-line live-glow face-off-bg">
                        <div class="flex items-center justify-between gap-4 px-6 pt-5 pb-4 lg:px-8">
                            <span class="live-marker">Live · {{ $liveMatch->table?->name }}</span>
                            <span class="badge badge-{{ str_replace('_', '-', $liveMatch->status) }}">{{ $liveMatch->status }}</span>
                        </div>

                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-6 px-6 py-10 lg:gap-12 lg:px-10 lg:py-14">
                            {{-- Home / red corner --}}
                            <div class="flex flex-col gap-2 text-left">
                                <span class="font-label text-red-corner-bright">Red Corner</span>
                                <span class="team-tag">
                                    <span class="team-dot" @if($home?->color) style="background-color: {{ $home->color }}" @endif></span>
                                    <span class="font-display text-[clamp(1.5rem,4vw,2.75rem)] text-stage-text">{{ $home?->name }}</span>
                                </span>
                            </div>

                            <div aria-hidden="true" class="face-off-divider h-24 w-px self-stretch"></div>

                            {{-- Away / blue corner --}}
                            <div class="flex flex-col gap-2 text-right">
                                <span class="font-label text-blue-corner-bright">Blue Corner</span>
                                <span class="team-tag justify-end">
                                    <span class="font-display text-[clamp(1.5rem,4vw,2.75rem)] text-stage-text">{{ $away?->name }}</span>
                                    <span class="team-dot" @if($away?->color) style="background-color: {{ $away->color }}" @endif></span>
                                </span>
                            </div>
                        </div>

                        <a href="{{ route('tournament.public', $tournament->public_token) }}"
                           class="block border-t border-stage-line/60 px-6 py-3 text-center text-sm font-medium text-stage-text-muted hover:bg-stage-bg/40 hover:text-stage-text transition lg:px-10">
                            Live verfolgen →
                        </a>
                    </section>
                @endif
            </main>

            <footer class="border-t border-stage-line">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5 text-xs text-stage-text-dim lg:px-10">
                    <span class="wordmark">pongtable</span>
                    <span>self-hosted · made for the Bierpong-WM</span>
                </div>
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
