<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stage-bg text-stage-text antialiased">
        <div class="relative flex min-h-screen flex-col">
            <header class="sticky top-0 z-20 border-b border-stage-line bg-stage-bg">
                <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-5 lg:px-10">
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
                        <a href="{{ route('rules') }}"
                           class="rounded-md px-4 py-2 font-medium text-stage-text-muted hover:text-stage-text hover:bg-stage-surface transition">
                            {{ __('Regeln') }}
                        </a>
                        <span aria-current="page"
                              class="rounded-md bg-stage-surface-2 px-4 py-2 font-medium text-stage-text">
                            {{ __('Turnierplan') }}
                        </span>
                    </nav>
                </div>
            </header>

            <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-12 px-6 pt-12 pb-16 lg:px-10 lg:pt-16">

                {{-- Hero --}}
                <section class="flex flex-col gap-5">
                    <div class="font-label flex items-center gap-3 text-trophy-gold">
                        <span class="block h-px w-12 bg-trophy-gold"></span>
                        <span>{{ $tournament->name }}</span>
                    </div>
                    <h1 class="font-display text-[clamp(2.5rem,7vw,5rem)] text-stage-text">Turnierplan</h1>
                    <p class="max-w-2xl text-base leading-relaxed text-stage-text-muted lg:text-lg">
                        Die geplante Reihenfolge der Spiele — eine grobe Orientierung, wann ihr an der Reihe seid.
                        Der Schiri kann jedes Spiel jederzeit starten; Abweichungen vom Plan sind also möglich.
                    </p>
                </section>

                {{-- Schedule list: one row per entered line --}}
                <section class="flex flex-col gap-5">
                    <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                        <h2 class="font-display text-2xl text-stage-text lg:text-3xl">Spielreihenfolge</h2>
                        <span class="font-label text-stage-text-dim">Gruppenphase</span>
                    </div>

                    <ol class="flex flex-col gap-3" data-test="schedule-list">
                        @foreach ($tournament->scheduleEntries() as $index => $teams)
                            <li class="flex items-center gap-4 rounded-lg bg-stage-surface px-5 py-4">
                                <span class="font-numeric shrink-0 text-sm font-semibold text-trophy-gold tabular-nums">
                                    {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                                </span>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-stage-text">
                                    @foreach ($teams as $team)
                                        @if (! $loop->first)
                                            <span class="font-label text-stage-text-dim">vs</span>
                                        @endif
                                        <span class="font-medium">{{ $team }}</span>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <div class="pt-4">
                    <a href="{{ route('home') }}" class="font-label text-stage-text-dim hover:text-stage-text transition">← Zur Startseite</a>
                </div>
            </main>

            <x-footer/>
        </div>

        @fluxScripts
    </body>
</html>
