<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stage-bg text-stage-text antialiased">
        @php
            $phaseLabels = [
                'setup' => 'Vorbereitung',
                'group' => 'Gruppenphase',
                'placement' => 'Platzierungsspiele',
                'ko' => 'KO-Phase',
                'finished' => 'Beendet',
            ];
            $publicTournament = $tournament ?? null;
        @endphp

        <header class="sticky top-0 z-20 border-b border-stage-line bg-stage-bg">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4 lg:px-10">
                <a href="{{ route('home') }}" class="flex items-baseline gap-3">
                    <span class="wordmark text-lg">pongtable</span>
                    @if ($publicTournament)
                        <span class="hidden text-stage-text-dim sm:inline">/</span>
                        <span class="hidden truncate text-sm font-medium text-stage-text-muted sm:inline-block sm:max-w-[40ch]">{{ $publicTournament->name }}</span>
                    @endif
                </a>

                <div class="flex items-center gap-3">
                    @if ($publicTournament)
                        <span class="font-label hidden text-stage-text-dim md:inline">
                            {{ $phaseLabels[$publicTournament->status] ?? $publicTournament->status }}
                        </span>
                    @endif
                    <x-appearance-toggle />
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="rounded-md border border-stage-line-strong px-3 py-1.5 text-sm font-medium text-stage-text hover:bg-stage-surface transition">
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="rounded-md px-3 py-1.5 text-sm font-medium text-stage-text-muted hover:text-stage-text hover:bg-stage-surface transition">
                            {{ __('Schiri-Login') }}
                        </a>
                    @endauth
                    <a href="{{ route('rules') }}"
                       class="rounded-md px-3 py-1.5 text-sm font-medium text-stage-text-muted hover:text-stage-text hover:bg-stage-surface transition">
                        {{ __('Regeln') }}
                    </a>
                    @if (\App\Models\Tournament::publicScheduleVisible())
                        <a href="{{ route('schedule') }}"
                           class="rounded-md px-3 py-1.5 text-sm font-medium text-stage-text-muted hover:text-stage-text hover:bg-stage-surface transition">
                            {{ __('Turnierplan') }}
                        </a>
                    @endif
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl px-6 py-8 lg:px-10 lg:py-12">
            {{ $slot }}
        </main>

        <x-footer/>

        @fluxScripts
    </body>
</html>
