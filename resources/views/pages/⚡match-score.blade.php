<?php

use App\Models\GameMatch;
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

        if ($match->status === 'pending') {
            app(MatchResultService::class)->startPreEntry($match);
        }

        $home = $match->stats->firstWhere('team_id', $match->home_team_id);
        $away = $match->stats->firstWhere('team_id', $match->away_team_id);

        $this->homeThrows = $home?->throws ?? 0;
        $this->homePenalty = $home?->penalty_cups ?? 0;
        $this->awayThrows = $away?->throws ?? 0;
        $this->awayPenalty = $away?->penalty_cups ?? 0;

        $this->homeCups = $home?->cups_scored ?? 0;
        $this->awayCups = $away?->cups_scored ?? 0;
    }

    #[Computed]
    public function match(): GameMatch
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'table', 'tournament', 'stats'])->findOrFail($this->matchId);
    }

    public function adjust(string $field, int $delta): void
    {
        $value = max(0, $this->{$field} + $delta);
        $this->{$field} = $value;
    }

    public function startMatch(MatchResultService $service): void
    {
        $service->startMatch($this->match, [
            'home_throws' => $this->homeThrows,
            'home_penalty_cups' => $this->homePenalty,
            'away_throws' => $this->awayThrows,
            'away_penalty_cups' => $this->awayPenalty,
        ]);
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
                    <span class="font-display text-stage-text text-[clamp(1.5rem,5vw,2.5rem)]">{{ $home?->name }}</span>
                </span>
            </div>
            <div aria-hidden="true" class="face-off-divider h-20 w-px self-stretch"></div>
            <div class="flex flex-col gap-2 text-right">
                <span class="font-label text-blue-corner-bright">Blue Corner</span>
                <span class="team-tag justify-end">
                    <span class="font-display text-stage-text text-[clamp(1.5rem,5vw,2.5rem)]">{{ $away?->name }}</span>
                    <span class="team-dot" @if($away?->color) style="background-color: {{ $away->color }}" @endif></span>
                </span>
            </div>
        </div>
    </header>

    {{-- ───────── PRE_ENTRY ───────── --}}
    @if (in_array($match->status, ['pending', 'pre_entry'], true))
        <section class="space-y-6">
            <div class="flex items-baseline justify-between">
                <h2 class="font-label text-stage-text-muted">Vor dem Spiel eintragen</h2>
                <span class="text-xs text-stage-text-dim">Würfe & Strafbecher pro Team</span>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ([
                    'home' => ['team' => $home, 'throws' => 'homeThrows', 'penalty' => 'homePenalty', 'corner' => 'Red', 'tint' => 'red'],
                    'away' => ['team' => $away, 'throws' => 'awayThrows', 'penalty' => 'awayPenalty', 'corner' => 'Blue', 'tint' => 'blue'],
                ] as $side => $cfg)
                    <div class="space-y-5 rounded-lg p-5 lg:p-6"
                         style="background: linear-gradient(180deg, var(--color-{{ $cfg['tint'] }}-corner-soft) 0%, transparent 100%), var(--color-stage-surface);">
                        <div class="flex items-baseline justify-between">
                            <span class="font-label text-{{ $cfg['tint'] }}-corner-bright">{{ $cfg['corner'] }} Corner</span>
                            <span class="team-tag">
                                <span class="team-dot" @if($cfg['team']?->color) style="background-color: {{ $cfg['team']->color }}" @endif></span>
                                <span class="font-display text-lg">{{ $cfg['team']?->name }}</span>
                            </span>
                        </div>

                        @foreach ([
                            ['label' => 'Würfe', 'field' => $cfg['throws']],
                            ['label' => 'Strafbecher', 'field' => $cfg['penalty']],
                        ] as $row)
                            <div class="flex items-center justify-between gap-3">
                                <label class="font-label text-stage-text-dim">{{ $row['label'] }}</label>
                                <div class="flex items-center gap-2">
                                    <button wire:click="adjust('{{ $row['field'] }}', -1)" type="button"
                                            class="h-14 w-14 rounded-md bg-stage-bg text-2xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition">&minus;</button>
                                    <input wire:model.live="{{ $row['field'] }}" type="number" min="0" inputmode="numeric"
                                           class="h-14 w-20 rounded-md border border-stage-line bg-stage-bg text-center font-numeric text-xl font-semibold text-stage-text focus:border-stage-line-strong">
                                    <button wire:click="adjust('{{ $row['field'] }}', 1)" type="button"
                                            class="h-14 w-14 rounded-md bg-stage-bg text-2xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition">+</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <button wire:click="startMatch"
                    class="w-full rounded-lg bg-stage-text px-5 py-5 text-lg font-bold tracking-wide text-stage-bg hover:bg-zinc-200 active:scale-[0.99] transition">
                Spiel starten
            </button>
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

            <div class="grid grid-cols-2 border-t border-stage-line">
                <div class="px-5 py-4 text-left lg:px-7 lg:py-5">
                    <span class="font-label text-red-corner-bright">Red &mdash; {{ $home?->name }}</span>
                    <p class="mt-1 text-sm text-stage-text-muted">
                        <span class="font-numeric text-stage-text">{{ $homeThrows }}</span> Würfe
                        <span class="mx-1 text-stage-text-dim">·</span>
                        <span class="font-numeric text-stage-text">{{ $homePenalty }}</span> Strafe
                    </p>
                </div>
                <div class="border-l border-stage-line px-5 py-4 text-right lg:px-7 lg:py-5">
                    <span class="font-label text-blue-corner-bright">Blue &mdash; {{ $away?->name }}</span>
                    <p class="mt-1 text-sm text-stage-text-muted">
                        <span class="font-numeric text-stage-text">{{ $awayThrows }}</span> Würfe
                        <span class="mx-1 text-stage-text-dim">·</span>
                        <span class="font-numeric text-stage-text">{{ $awayPenalty }}</span> Strafe
                    </p>
                </div>
            </div>
        </section>

        <button wire:click="endTimer" wire:confirm="Runde wirklich beenden?"
                class="w-full rounded-lg border border-status-danger bg-status-danger-soft px-5 py-5 text-lg font-bold tracking-wide text-status-danger hover:bg-stage-surface-2 active:scale-[0.99] transition">
            Runde beenden
        </button>
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
                    class="w-full rounded-lg bg-stage-text px-5 py-5 text-lg font-bold tracking-wide text-stage-bg hover:bg-zinc-200 active:scale-[0.99] transition">
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
