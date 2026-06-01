<?php

use App\Models\GameMatch;
use App\Models\MatchStat;
use App\Services\MatchResultService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Match')] class extends Component {
    public int $matchId;

    public int $homeThrows = 0;
    public int $homePenalty = 0;
    public int $awayThrows = 0;
    public int $awayPenalty = 0;

    public int $homeCups = 0;
    public int $awayCups = 0;

    public function mount(GameMatch $match): void
    {
        $this->matchId = $match->id;

        if ($match->status === 'pending' && $match->home_team_id !== null && $match->away_team_id !== null) {
            app(MatchResultService::class)->startPreEntry($match);
        }

        $home = $match->stats->firstWhere('team_id', $match->home_team_id);
        $away = $match->stats->firstWhere('team_id', $match->away_team_id);

        $this->homeThrows = $home?->throws ?? 0;
        $this->homePenalty = $home?->penalty_cups ?? 0;
        $this->awayThrows = $away?->throws ?? 0;
        $this->awayPenalty = $away?->penalty_cups ?? 0;

        // Cups: bei Bierpong gibt es 10 Becher und der Sieger hat fast immer
        // alle. 10:10 vorbelegen → Schiri zählt nur vom Verliererteam runter.
        // Für bereits abgeschlossene Matches die echten Werte laden.
        if ($match->status === 'finished') {
            $this->homeCups = $home?->cups_scored ?? 0;
            $this->awayCups = $away?->cups_scored ?? 0;
        } else {
            $this->homeCups = ($home?->cups_scored ?: 10);
            $this->awayCups = ($away?->cups_scored ?: 10);
        }
    }

    #[Computed]
    public function match(): GameMatch
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'table', 'tournament', 'stats'])->findOrFail($this->matchId);
    }

    /** @var array<string, array{string, string}> */
    protected const LIVE_FIELD_MAP = [
        'homeThrows' => ['home', 'throws'],
        'homePenalty' => ['home', 'penalty_cups'],
        'awayThrows' => ['away', 'throws'],
        'awayPenalty' => ['away', 'penalty_cups'],
    ];

    public function adjust(string $field, int $delta): void
    {
        $value = max(0, $this->{$field} + $delta);
        $this->{$field} = $value;

        // Livewire `updated*` hooks only fire for client-driven property changes
        // (wire:model). +/- buttons mutate state on the server, so we must
        // persist explicitly here.
        $this->persistFromField($field);
    }

    public function updatedHomeThrows(): void { $this->persistFromField('homeThrows'); }
    public function updatedHomePenalty(): void { $this->persistFromField('homePenalty'); }
    public function updatedAwayThrows(): void { $this->persistFromField('awayThrows'); }
    public function updatedAwayPenalty(): void { $this->persistFromField('awayPenalty'); }

    protected function persistFromField(string $field): void
    {
        if (! isset(self::LIVE_FIELD_MAP[$field])) {
            return;
        }

        [$side, $column] = self::LIVE_FIELD_MAP[$field];
        $this->persistLiveStat($side, $column, (int) $this->{$field});
    }

    /**
     * Persist a live throw/penalty edit. Only fires during the active phase —
     * pre_entry stays empty by design (entry happens DURING the match now),
     * scoring would let live cups leak into the running totals.
     */
    protected function persistLiveStat(string $side, string $column, int $value): void
    {
        $match = $this->match;

        if ($match->status !== 'active') {
            return;
        }

        $teamId = $side === 'home' ? $match->home_team_id : $match->away_team_id;

        MatchStat::updateOrCreate(
            ['match_id' => $match->id, 'team_id' => $teamId],
            [$column => max(0, $value)],
        );

        unset($this->match);
    }

    public function startMatch(MatchResultService $service): void
    {
        // Throws & penalties are entered LIVE during the active phase, not before.
        // We hand zeros to the service so it can still seed the MatchStat rows.
        $service->startMatch($this->match, [
            'home_throws' => 0,
            'home_penalty_cups' => 0,
            'away_throws' => 0,
            'away_penalty_cups' => 0,
        ]);

        $this->homeThrows = 0;
        $this->homePenalty = 0;
        $this->awayThrows = 0;
        $this->awayPenalty = 0;

        unset($this->match);
    }

    public function endTimer(MatchResultService $service): void
    {
        $service->endTimer($this->match);
        unset($this->match);
    }

    public function saveResult(MatchResultService $service): void
    {
        $this->validate([
            'homeCups' => 'required|integer|min:0|max:30',
            'awayCups' => 'required|integer|min:0|max:30',
        ]);

        $service->saveResult($this->match, $this->homeCups, $this->awayCups);
        unset($this->match);

        Flux::toast(variant: 'success', text: __('Ergebnis gespeichert.'));
    }
}; ?>

<div class="mx-auto w-full max-w-3xl space-y-6 p-4 lg:p-6"
     @if($this->match->status === 'active') wire:poll.1s @else wire:poll.3s @endif>
    @php
        $match = $this->match;
        $home = $match->homeTeam;
        $away = $match->awayTeam;
        $durationMinutes = $match->phase === 'group'
            ? $match->tournament->group_match_duration_minutes
            : $match->tournament->ko_match_duration_minutes;
        $startedAtIso = $match->started_at?->toIso8601String();
    @endphp

    {{-- Back link --}}
    <a href="{{ route('matches.index') }}" wire:navigate
       class="inline-flex items-center gap-2 text-sm text-stage-text-muted hover:text-stage-text transition">
        <flux:icon.arrow-left class="size-4" />
        Zurück zur Match-Liste
    </a>

    {{-- Header: face-off framing --}}
    <header class="relative overflow-hidden rounded-lg face-off-bg">
        <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-3 lg:px-6">
            <div class="font-label flex items-center gap-3 text-stage-text-muted">
                <span>{{ $match->table?->name }}</span>
                <span class="text-stage-text-dim">·</span>
                <span>
                    @if ($match->phase === 'group')
                        Gruppenphase
                    @else
                        KO &middot; @switch($match->ko_round)
                            @case(1) Finale @break
                            @case(2) Halbfinale @break
                            @case(4) Viertelfinale @break
                            @case(8) Achtelfinale @break
                            @default Runde {{ $match->ko_round }}
                        @endswitch
                    @endif
                </span>
            </div>
            <span class="badge badge-{{ str_replace('_', '-', $match->status) }}">{{ $match->status }}</span>
        </div>

        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 px-5 py-7 lg:gap-8 lg:px-8 lg:py-10">
            <div class="flex flex-col gap-2 text-left">
                <span class="font-label text-red-corner-bright">Red Corner</span>
                <span class="team-tag">
                    <span class="team-dot" @if($home?->color) style="background-color: {{ $home->color }}" @endif></span>
                    @if ($home)
                        <span class="font-display text-stage-text text-[clamp(1.5rem,5vw,2.5rem)]">{{ $home->name }}</span>
                    @else
                        <span class="font-display text-stage-text-dim text-[clamp(1.5rem,5vw,2.5rem)]">Sieger Vorrunde</span>
                    @endif
                </span>
            </div>
            <div aria-hidden="true" class="face-off-divider h-20 w-px self-stretch"></div>
            <div class="flex flex-col gap-2 text-right">
                <span class="font-label text-blue-corner-bright">Blue Corner</span>
                <span class="team-tag justify-end">
                    @if ($away)
                        <span class="font-display text-stage-text text-[clamp(1.5rem,5vw,2.5rem)]">{{ $away->name }}</span>
                    @else
                        <span class="font-display text-stage-text-dim text-[clamp(1.5rem,5vw,2.5rem)]">Sieger Vorrunde</span>
                    @endif
                    <span class="team-dot" @if($away?->color) style="background-color: {{ $away->color }}" @endif></span>
                </span>
            </div>
        </div>
    </header>

    {{-- ───────── PRE_ENTRY ─────────
         No inputs here on purpose — Würfe & Strafe are entered LIVE during
         the active phase. Pre-entry exists only as a "Teams, an den Tisch"
         signal between matches. KO matches whose opponent is still in play
         render a "wartet auf Vorrunde" state instead. --}}
    @if (in_array($match->status, ['pending', 'pre_entry'], true))
        @php $teamsReady = $match->home_team_id !== null && $match->away_team_id !== null; @endphp
        <section class="space-y-6">
            @if ($teamsReady)
                <div class="rounded-lg face-off-bg px-6 py-10 text-center lg:px-10 lg:py-14">
                    <span class="font-label text-trophy-gold">Bereitmachen</span>
                    <h2 class="mt-3 font-display text-stage-text text-[clamp(1.75rem,5vw,2.75rem)]">
                        Teams an den Tisch
                    </h2>
                    <p class="mt-3 text-sm text-stage-text-muted lg:text-base">
                        Würfe und Strafbecher werden während des laufenden Spiels gezählt.
                    </p>
                </div>

                <button wire:click="startMatch"
                        class="w-full rounded-lg bg-stage-text px-5 py-5 text-lg font-bold tracking-wide text-stage-bg hover:opacity-90 active:scale-[0.99] transition">
                    Spiel starten
                </button>
            @else
                <div class="rounded-lg border border-stage-line bg-stage-surface px-6 py-10 text-center lg:px-10 lg:py-14">
                    <span class="font-label text-stage-text-dim">Noch nicht spielbereit</span>
                    <h2 class="mt-3 font-display text-stage-text text-[clamp(1.5rem,4vw,2.25rem)]">
                        Wartet auf Vorrunde
                    </h2>
                    <p class="mt-3 max-w-md mx-auto text-sm text-stage-text-muted lg:text-base">
                        @if ($match->home_team_id === null && $match->away_team_id === null)
                            Beide Vorrundenspiele müssen noch beendet werden.
                        @else
                            Das Vorrundenspiel der Gegenseite läuft noch. Sobald der Sieger feststeht, kann es hier weitergehen.
                        @endif
                    </p>
                </div>
            @endif
        </section>
    @endif

    {{-- ───────── ACTIVE / TIMER ───────── --}}
    @if ($match->status === 'active')
        <section class="relative overflow-hidden rounded-lg face-off-bg live-glow"
                 x-data="{
                     totalSec: {{ $durationMinutes }} * 60,
                     startedAt: '{{ $startedAtIso }}' ? new Date('{{ $startedAtIso }}').getTime() : Date.now(),
                     display: '00:00',
                     timerClass: 'timer-ok',
                     handle: null,
                     tick() {
                         const elapsed = Math.floor((Date.now() - this.startedAt) / 1000);
                         const remaining = this.totalSec - elapsed;
                         const abs = Math.abs(remaining);
                         const mm = String(Math.floor(abs / 60)).padStart(2, '0');
                         const ss = String(abs % 60).padStart(2, '0');
                         this.display = (remaining < 0 ? '-' : '') + mm + ':' + ss;
                         if (remaining < 0) this.timerClass = 'timer-overtime';
                         else if (remaining <= 120) this.timerClass = 'timer-warning';
                         else this.timerClass = 'timer-ok';
                     },
                 }"
                 x-init="tick(); handle = setInterval(() => tick(), 500)"
                 x-destroy="clearInterval(handle)">

            <div class="flex flex-col items-center gap-3 px-6 pt-12 pb-8 lg:pt-16 lg:pb-12">
                <span class="live-marker">Live</span>
                <div class="font-numeric text-[clamp(5rem,18vw,9rem)] font-bold leading-none tracking-tight"
                     :class="timerClass">
                    <span x-text="display"></span>
                </div>
                <span class="font-label text-stage-text-dim">Verbleibende Zeit</span>
            </div>

            <div class="grid grid-cols-1 border-t border-stage-line md:grid-cols-2">
                @foreach ([
                    'home' => ['team' => $home, 'throws' => 'homeThrows', 'penalty' => 'homePenalty', 'corner' => 'Red', 'tint' => 'red'],
                    'away' => ['team' => $away, 'throws' => 'awayThrows', 'penalty' => 'awayPenalty', 'corner' => 'Blue', 'tint' => 'blue'],
                ] as $side => $cfg)
                    <div @class([
                        'space-y-4 px-5 py-5 lg:px-7 lg:py-6',
                        'border-t border-stage-line md:border-t-0 md:border-l' => $side === 'away',
                    ])>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-label text-{{ $cfg['tint'] }}-corner-bright">{{ $cfg['corner'] }} &mdash; {{ $cfg['team']?->name }}</span>
                            <span class="team-dot" @if($cfg['team']?->color) style="background-color: {{ $cfg['team']->color }}" @endif></span>
                        </div>

                        @foreach ([
                            ['label' => 'Würfe', 'field' => $cfg['throws']],
                            ['label' => 'Strafe', 'field' => $cfg['penalty']],
                        ] as $row)
                            <div class="flex items-center justify-between gap-3">
                                <label class="font-label text-stage-text-dim">{{ $row['label'] }}</label>
                                <div class="flex items-center gap-2">
                                    <button wire:click="adjust('{{ $row['field'] }}', -1)" type="button"
                                            class="h-11 w-11 rounded-md bg-stage-bg/60 text-xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition">&minus;</button>
                                    <input wire:model.live="{{ $row['field'] }}" name="{{ $row['field'] }}" type="number" min="0" inputmode="numeric"
                                           class="h-11 w-16 rounded-md border border-stage-line bg-stage-bg/60 text-center font-numeric text-lg font-semibold text-stage-text focus:border-stage-line-strong">
                                    <button wire:click="adjust('{{ $row['field'] }}', 1)" type="button"
                                            class="h-11 w-11 rounded-md bg-stage-bg/60 text-xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition">+</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>

        <flux:modal.trigger name="confirm-end-round">
            <button type="button"
                    class="w-full rounded-lg border border-status-danger bg-status-danger-soft px-5 py-5 text-lg font-bold tracking-wide text-status-danger hover:bg-stage-surface-2 active:scale-[0.99] transition">
                Runde beenden
            </button>
        </flux:modal.trigger>

        <flux:modal name="confirm-end-round" class="md:w-104">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Runde beenden?</flux:heading>
                    <flux:text class="mt-2">
                        Sobald die Runde endet, geht es weiter zur Becher-Eingabe. Würfe und Strafe lassen sich danach nicht mehr ändern.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="endTimer" variant="danger" data-test="confirm-end-round">
                        Ja, Runde beenden
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- ───────── SCORING ───────── --}}
    @if ($match->status === 'scoring')
        <section class="space-y-6">
            <div class="flex items-baseline justify-between">
                <h2 class="font-label text-stage-text-muted">Getroffene Becher eintragen</h2>
                <span class="text-xs text-stage-text-dim">Hauptmetrik · entscheidet Sieg</span>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ([
                    'home' => ['team' => $home, 'field' => 'homeCups', 'corner' => 'Red', 'tint' => 'red'],
                    'away' => ['team' => $away, 'field' => 'awayCups', 'corner' => 'Blue', 'tint' => 'blue'],
                ] as $side => $cfg)
                    <div class="space-y-4 rounded-lg p-6"
                         style="background: linear-gradient(180deg, var(--color-{{ $cfg['tint'] }}-corner-soft) 0%, transparent 100%), var(--color-stage-surface);">
                        <div class="flex items-baseline justify-between">
                            <span class="font-label text-{{ $cfg['tint'] }}-corner-bright">{{ $cfg['corner'] }} Corner</span>
                            <span class="team-tag">
                                <span class="team-dot" @if($cfg['team']?->color) style="background-color: {{ $cfg['team']->color }}" @endif></span>
                                <span class="font-display text-lg">{{ $cfg['team']?->name }}</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-center gap-4 pt-2">
                            <button wire:click="adjust('{{ $cfg['field'] }}', -1)" type="button"
                                    class="h-16 w-16 rounded-lg bg-stage-bg text-3xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition">&minus;</button>
                            <input wire:model.live="{{ $cfg['field'] }}" name="{{ $cfg['field'] }}" type="number" min="0" max="30" inputmode="numeric"
                                   class="h-24 w-28 rounded-lg border border-stage-line bg-stage-bg text-center font-numeric text-5xl font-bold text-stage-text focus:border-stage-line-strong">
                            <button wire:click="adjust('{{ $cfg['field'] }}', 1)" type="button"
                                    class="h-16 w-16 rounded-lg bg-stage-bg text-3xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition">+</button>
                        </div>
                    </div>
                @endforeach
            </div>

            @error('homeCups') <p class="text-sm text-status-danger">{{ $message }}</p> @enderror
            @error('awayCups') <p class="text-sm text-status-danger">{{ $message }}</p> @enderror

            <button wire:click="saveResult"
                    class="w-full rounded-lg bg-stage-text px-5 py-5 text-lg font-bold tracking-wide text-stage-bg hover:opacity-90 active:scale-[0.99] transition">
                Ergebnis speichern
            </button>
        </section>
    @endif

    {{-- ───────── FINISHED ───────── --}}
    @if ($match->status === 'finished')
        @php
            $winner = $match->winnerTeam;
            $homeStat = $match->stats->firstWhere('team_id', $match->home_team_id);
            $awayStat = $match->stats->firstWhere('team_id', $match->away_team_id);
            $duration = $homeStat?->duration_seconds ?? $awayStat?->duration_seconds;
            $homeWin = $match->winner_team_id === $match->home_team_id;
        @endphp
        <section class="overflow-hidden rounded-lg border border-trophy-gold/40 bg-trophy-gold-soft">
            <div class="px-6 py-10 lg:px-10 lg:py-14">
                <div class="font-label flex items-center gap-3 text-trophy-gold">
                    <span class="block h-px w-12 bg-trophy-gold"></span>
                    <span>Sieger</span>
                </div>
                <h2 class="mt-4 font-display text-trophy-gold text-[clamp(2rem,7vw,4rem)]">
                    {{ $winner?->name }}
                </h2>
                <p class="mt-6 font-numeric text-[clamp(2rem,6vw,3.5rem)] font-bold text-stage-text">
                    <span class="@if($homeWin) text-trophy-gold @else text-stage-text-muted @endif">{{ $homeStat?->cups_scored ?? 0 }}</span>
                    <span class="mx-3 text-stage-text-dim">:</span>
                    <span class="@if(! $homeWin) text-trophy-gold @else text-stage-text-muted @endif">{{ $awayStat?->cups_scored ?? 0 }}</span>
                </p>
            </div>

            <dl class="grid grid-cols-3 border-t border-trophy-gold/30">
                <div class="border-r border-trophy-gold/30 px-5 py-4 text-center lg:py-5">
                    <dt class="font-label text-stage-text-dim">Dauer</dt>
                    <dd class="mt-1 font-numeric text-lg font-semibold text-stage-text">
                        {{ $duration ? sprintf('%02d:%02d', intdiv($duration, 60), $duration % 60) : '—' }}
                    </dd>
                </div>
                <div class="border-r border-trophy-gold/30 px-5 py-4 text-center lg:py-5">
                    <dt class="font-label text-stage-text-dim">Würfe</dt>
                    <dd class="mt-1 font-numeric text-lg font-semibold text-stage-text">{{ $homeStat?->throws ?? 0 }} : {{ $awayStat?->throws ?? 0 }}</dd>
                </div>
                <div class="px-5 py-4 text-center lg:py-5">
                    <dt class="font-label text-stage-text-dim">Strafe</dt>
                    <dd class="mt-1 font-numeric text-lg font-semibold text-stage-text">{{ $homeStat?->penalty_cups ?? 0 }} : {{ $awayStat?->penalty_cups ?? 0 }}</dd>
                </div>
            </dl>
        </section>

        <a href="{{ route('matches.index') }}" wire:navigate
           class="inline-flex items-center gap-2 rounded-md border border-stage-line-strong px-5 py-3 text-sm font-medium text-stage-text hover:bg-stage-surface transition">
            <flux:icon.arrow-left class="size-4" />
            Zurück zur Match-Liste
        </a>
    @endif
</div>
