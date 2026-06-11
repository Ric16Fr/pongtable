<x-layouts::public :title="$tournament->name" :tournament="$tournament">
    @php
        $tiles = [];
        if ($stats) {
            if ($stats['sharpest_shooter'] ?? null) {
                $tiles[] = ['key' => 'shooter', 'label' => 'Schärfste Schützen', 'value' => $stats['sharpest_shooter']['team'], 'sub' => $stats['sharpest_shooter']['rate'].'% Quote · '.$stats['sharpest_shooter']['scored'].'/'.$stats['sharpest_shooter']['throws']];
            }
            if ($stats['water_spitter'] ?? null) {
                $tiles[] = ['key' => 'spitter', 'label' => 'Wasserspeier', 'value' => $stats['water_spitter']['team'], 'sub' => $stats['water_spitter']['rate'].'% Trefferquote'];
            }
            if ($stats['blitz_win'] ?? null) {
                $d = $stats['blitz_win']['duration'];
                $tiles[] = ['key' => 'blitz', 'label' => 'Blitzsieg', 'value' => $stats['blitz_win']['team'], 'sub' => sprintf('%02d:%02d gegen %s', intdiv($d, 60), $d % 60, $stats['blitz_win']['opponent'])];
            }
            if ($stats['marathon'] ?? null) {
                $d = $stats['marathon']['duration'];
                $tiles[] = ['key' => 'marathon', 'label' => 'Marathonspieler', 'value' => implode(' vs ', $stats['marathon']['teams']), 'sub' => sprintf('%02d:%02d', intdiv($d, 60), $d % 60)];
            }
            if ($stats['nail_biter'] ?? null) {
                $tiles[] = ['key' => 'nail_biter', 'label' => 'Knapper Krimi', 'value' => implode(' vs ', $stats['nail_biter']['teams']), 'sub' => $stats['nail_biter']['score'].' · '.($stats['nail_biter']['diff'] === 0 ? 'Patt' : ($stats['nail_biter']['diff'].' Becher Differenz'))];
            }
            if ($stats['penalty_magnet'] ?? null) {
                $tiles[] = ['key' => 'penalty', 'label' => 'Strafbechermagnet', 'value' => $stats['penalty_magnet']['team'], 'sub' => $stats['penalty_magnet']['penalty_cups'].' Strafbecher'];
            }
            if ($stats['efficiency'] ?? null) {
                $tiles[] = ['key' => 'efficiency', 'label' => 'Effizienzrate', 'value' => $stats['efficiency']['team'], 'sub' => $stats['efficiency']['rate'].'%'];
            }
            if ($stats['schluck_olymp'] ?? null) {
                $tiles[] = ['key' => 'schluck_olymp', 'label' => 'Schluck-Olymp', 'value' => $stats['schluck_olymp']['team'], 'sub' => $stats['schluck_olymp']['cups'].' Becher geleert'];
            }
        }
        $phaseLabels = [
            'setup' => 'Vorbereitung',
            'group' => 'Gruppenphase',
            'placement' => 'Platzierungsspiele',
            'ko' => 'KO-Phase',
            'finished' => 'Beendet',
        ];

        $totalMatches = $tournament->matches()->count();
        $finishedMatches = $tournament->matches()->where('status', 'finished')->count();
        $progressPct = $totalMatches > 0 ? round(($finishedMatches / $totalMatches) * 100) : 0;

        $tournamentStartedAt = $tournament->matches()->whereNotNull('started_at')->min('started_at');
        $startedAtMs = $tournamentStartedAt ? \Illuminate\Support\Carbon::parse($tournamentStartedAt)->getTimestampMs() : null;

        $isRunning = in_array($tournament->status, ['group', 'placement', 'ko'], true);

        // Champion meta: real match wins for the personality subhead
        $championWins = null;
        if ($tournament->isFinished() && ($stats['champion'] ?? null)) {
            $finalWinnerId = $tournament->matches()
                ->where('phase', 'ko')
                ->where('ko_round', 1)
                ->where('status', 'finished')
                ->value('winner_team_id');
            $championWins = $finalWinnerId
                ? $tournament->matches()->where('winner_team_id', $finalWinnerId)->count()
                : null;
        }
    @endphp

    <div class="space-y-16">
        {{-- Title moment — calibrated for the back of the room --}}
        <header class="flex flex-col gap-5">
            <div class="font-label flex flex-wrap items-center gap-x-3 gap-y-1 text-trophy-gold">
                <span class="block h-px w-12 bg-trophy-gold"></span>
                <span>{{ $phaseLabels[$tournament->status] ?? $tournament->status }}</span>

                @if ($isRunning && $startedAtMs)
                    {{-- Live tournament clock. Updates client-side every 30s; never blocks render. --}}
                    <span class="text-stage-text-dim">·</span>
                    <span class="text-stage-text-muted"
                          x-data="{
                              startedAt: {{ $startedAtMs }},
                              display: '',
                              tick() {
                                  const elapsedSec = Math.max(0, Math.floor((Date.now() - this.startedAt) / 1000));
                                  const h = Math.floor(elapsedSec / 3600);
                                  const m = Math.floor((elapsedSec % 3600) / 60);
                                  this.display = h > 0
                                      ? h + 'h ' + String(m).padStart(2, '0') + 'm'
                                      : m + 'm';
                              },
                              handle: null,
                          }"
                          x-init="tick(); handle = setInterval(() => tick(), 30000)"
                          x-destroy="clearInterval(handle)">
                        Läuft seit <span class="font-numeric text-stage-text" x-text="display">…</span>
                    </span>
                @elseif ($tournament->isFinished())
                    <span class="text-stage-text-dim">·</span>
                    <span class="text-stage-text-muted">Letzte Runde gespielt</span>
                @endif
            </div>

            <h1 class="font-display text-[clamp(2.5rem,7vw,5.5rem)] text-stage-text">
                {{ $tournament->name }}
            </h1>

            {{-- Match progress meter. Gold trim earns its place here: the bar's fill
                 is the journey toward a champion, exactly the "moment of glory" the
                 Gold-For-Glory rule names. Hidden during setup and after the final. --}}
            @if ($isRunning && $totalMatches > 0)
                <div class="flex flex-col gap-2 pt-2">
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <span class="font-label text-stage-text-muted">
                            <span class="font-numeric text-base font-semibold text-stage-text">{{ $finishedMatches }}</span>
                            <span class="text-stage-text-dim">/</span>
                            <span class="font-numeric text-stage-text-muted">{{ $totalMatches }}</span>
                            {{ $finishedMatches === 1 ? 'Match gespielt' : 'Matches gespielt' }}
                        </span>
                        <span class="font-numeric text-sm font-bold text-trophy-gold">{{ $progressPct }}%</span>
                        @if ($progressPct >= 100)
                            <span class="font-label text-stage-text-dim">letzter Becher fällt gleich</span>
                        @elseif ($progressPct >= 80)
                            <span class="font-label text-stage-text-dim">Endspurt</span>
                        @endif
                    </div>
                    <div class="h-[2px] w-full max-w-md overflow-hidden rounded-full bg-stage-line">
                        <div class="h-full rounded-full bg-trophy-gold transition-[width] duration-1000 ease-out"
                             style="width: {{ $progressPct }}%"></div>
                    </div>
                </div>
            @endif
        </header>

        {{-- Champion banner: only after the final whistle. Full-bleed, drenched. --}}
        @if ($tournament->isFinished() && ($stats['champion'] ?? null))
            @php
                $cupsTotal = $stats['total_cups'] ?? 0;
                $championCopy = $championWins
                    ? trans_choice('Hat sich durch :count Match nach oben getrunken. Schluck für Schluck zum Pott.|Hat sich durch :count Matches nach oben getrunken. Schluck für Schluck zum Pott.', $championWins, ['count' => $championWins])
                    : 'Steht oben. Punkt.';
            @endphp
            <section class="overflow-hidden rounded-lg border border-trophy-gold/40 bg-trophy-gold-soft px-6 py-10 lg:px-12 lg:py-16">
                <div class="font-label flex items-center gap-3 text-trophy-gold">
                    <span class="block h-px w-12 bg-trophy-gold"></span>
                    <span>Turniersieger</span>
                </div>
                <h2 class="mt-4 font-display text-trophy-gold text-[clamp(2.75rem,8vw,6.5rem)]">
                    {{ $stats['champion']['name'] }}
                </h2>
                <p class="mt-6 max-w-2xl text-base text-stage-text-muted lg:text-lg">
                    {{ $championCopy }}
                </p>
                @if ($cupsTotal > 0)
                    <p class="mt-2 font-label text-stage-text-dim">
                        Insgesamt <span class="font-numeric text-stage-text">{{ $cupsTotal }}</span>
                        {{ $cupsTotal === 1 ? 'Becher' : 'Becher' }} im ganzen Turnier geleert.
                    </p>
                @endif
            </section>
        @endif

        {{-- The bracket: the showpiece. Renders its own internal structure. --}}
        <livewire:pages::tournament-bracket :tournament-id="$tournament->id" />

        {{-- Leaderboard. Section heading typographic, table renders itself. --}}
        <section class="space-y-3">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-label text-stage-text-muted">Leaderboard</h2>
                <span class="font-label text-stage-text-dim">Punkte vor Cup-Differenz vor Treffern</span>
            </div>
            <livewire:pages::leaderboard :tournament-id="$tournament->id" />
        </section>

        {{-- Fun stats after the final. Asymmetric grid, varied tile spans,
             explicitly NOT the banned identical-card pattern. --}}
        @if ($tournament->isFinished() && ! empty($tiles))
            <section id="stats" class="space-y-6">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="font-label text-stage-text-muted">Bilanz</h2>
                    <span class="font-label text-stage-text-dim">was zwischen den Bechern passiert ist</span>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                    @foreach ($tiles as $i => $tile)
                        @php
                            // Hand-curated span pattern so the grid never feels identical.
                            $span = match (true) {
                                $i === 0 => 'sm:col-span-4',
                                $i === 1 => 'sm:col-span-2',
                                $i % 5 === 2 => 'sm:col-span-3',
                                $i % 5 === 3 => 'sm:col-span-3',
                                $i % 5 === 4 => 'sm:col-span-2',
                                default => 'sm:col-span-4',
                            };
                        @endphp
                        <div class="{{ $span }} flex flex-col justify-between gap-3 rounded-lg bg-stage-surface px-5 py-5 lg:px-6 lg:py-6">
                            <span class="font-label text-stage-text-dim">{{ $tile['label'] }}</span>
                            <div class="flex flex-col gap-1">
                                <span class="font-display text-stage-text text-2xl leading-tight lg:text-3xl">{{ $tile['value'] }}</span>
                                @if ($tile['sub'])
                                    <span class="font-numeric text-sm text-stage-text-muted">{{ $tile['sub'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="sm:col-span-6 flex items-baseline justify-between border-t border-stage-line pt-5">
                        <span class="font-label text-stage-text-muted">Becher geleert</span>
                        <span class="font-numeric text-3xl font-semibold text-stage-text lg:text-4xl">{{ $stats['total_cups'] ?? 0 }}</span>
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-layouts::public>
