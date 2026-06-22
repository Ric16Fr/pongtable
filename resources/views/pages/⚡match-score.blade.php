<?php

use App\Models\GameMatch;
use App\Models\MatchMemberCup;
use App\Models\MatchStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\MatchResultService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Match')]
class extends Component {
    public int $matchId;

    public int $homeThrows = 0;
    public int $homePenalty = 0;
    public int $awayThrows = 0;
    public int $awayPenalty = 0;

    public int $homeCups = 0;
    public int $awayCups = 0;

    public ?int $suddenDeathWinner = null;

    /**
     * Cups hit per team member for the cup-king rule, keyed by team_member id.
     *
     * @var array<int, int>
     */
    public array $cupDistribution = [];

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

    /**
     * A KO match that ended tied while the sudden-death rule is active —
     * the referee must pick the winning team manually.
     */
    #[Computed]
    public function needsSuddenDeath(): bool
    {
        return $this->match->phase === 'ko'
            && ($this->match->tournament->ko_sudden_death ?? false)
            && $this->homeCups === $this->awayCups;
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

    public function updatedHomeThrows(): void
    {
        $this->persistFromField('homeThrows');
    }

    public function updatedHomePenalty(): void
    {
        $this->persistFromField('homePenalty');
    }

    public function updatedAwayThrows(): void
    {
        $this->persistFromField('awayThrows');
    }

    public function updatedAwayPenalty(): void
    {
        $this->persistFromField('awayPenalty');
    }

    protected function persistFromField(string $field): void
    {
        if (!isset(self::LIVE_FIELD_MAP[$field])) {
            return;
        }

        [$side, $column] = self::LIVE_FIELD_MAP[$field];
        $this->persistLiveStat($side, $column, (int)$this->{$field});
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

        if ($this->needsSuddenDeath) {
            $validWinners = array_filter([$this->match->home_team_id, $this->match->away_team_id]);

            if (!in_array($this->suddenDeathWinner, $validWinners, true)) {
                $this->addError('suddenDeathWinner', __('Bitte das siegreiche Team des Sudden Death auswählen.'));

                return;
            }
        }

        $service->saveResult(
            $this->match,
            $this->homeCups,
            $this->awayCups,
            $this->needsSuddenDeath ? $this->suddenDeathWinner : null,
        );
        unset($this->match);

        Flux::toast(variant: 'success', text: __('Ergebnis gespeichert.'));

        // Cup-king rule: after the result is saved (and the winner box shown),
        // offer the bonus modal to spread the scored cups across the players.
        if ($this->match->tournament->determine_cup_king ?? false) {
            $this->prepareCupDistribution();
            Flux::modal('distribute-cups')->show();
        }
    }

    /**
     * The two teams of this match, each with their named members, for the
     * cup-distribution modal.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function distributionTeams(): Collection
    {
        return collect([$this->match->homeTeam, $this->match->awayTeam])
            ->filter()
            ->each(fn($team) => $team->loadMissing('members'))
            ->values();
    }

    /**
     * Pre-fill the cup distribution from any existing values for this match.
     */
    protected function prepareCupDistribution(): void
    {
        $existing = MatchMemberCup::query()
            ->where('match_id', $this->matchId)
            ->pluck('cups_hit', 'team_member_id');

        $this->cupDistribution = [];

        foreach ($this->distributionTeams as $team) {
            foreach ($team->members as $member) {
                $this->cupDistribution[$member->id] = (int)($existing[$member->id] ?? 0);
            }
        }
    }

    /**
     * Persist the cup distribution for this match (replacing any prior values).
     * No sum validation by design — penalty cups break the equality anyway.
     */
    public function saveCupDistribution(): void
    {
        $match = $this->match;

        $validMemberIds = TeamMember::query()
            ->whereIn('team_id', array_filter([$match->home_team_id, $match->away_team_id]))
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($match, $validMemberIds) {
            MatchMemberCup::where('match_id', $match->id)->delete();

            foreach ($this->cupDistribution as $memberId => $cups) {
                if (!in_array((int)$memberId, $validMemberIds, true)) {
                    continue;
                }

                MatchMemberCup::create([
                    'match_id' => $match->id,
                    'team_member_id' => (int)$memberId,
                    'cups_hit' => max(0, (int)$cups),
                ]);
            }
        });

        Flux::modal('distribute-cups')->close();
        Flux::toast(variant: 'success', text: __('Becher verteilt.'));
    }
}; ?>

<div class="mx-auto w-full max-w-3xl space-y-6 p-4 lg:p-6"
     @if($this->match->status === 'active') wire:poll.1s @else wire:poll.3s @endif>
    @php
        $match = $this->match;
        $home = $match->homeTeam;
        $away = $match->awayTeam;
        $durationMinutes = in_array($match->phase, ['group', 'placement'], true)
            ? $match->tournament->group_match_duration_minutes
            : $match->tournament->ko_match_duration_minutes;
        $startedAtIso = $match->started_at?->toIso8601String();
        $countThrows = $match->tournament->count_throws ?? true;
    @endphp

    {{-- Back link --}}
    <a href="{{ route('matches.index') }}" wire:navigate
       class="inline-flex items-center gap-2 text-sm text-stage-text-muted hover:text-stage-text transition" >
        <flux:icon.arrow-left class="size-4" />
        Zurück zur Match-Liste
    </a >

    {{-- Header: face-off framing --}}
    <header class="relative overflow-hidden rounded-lg face-off-bg" >
        <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-3 lg:px-6" >
            <div class="font-label flex items-center gap-3 text-stage-text-muted" >
                <span >{{ $match->table?->name }}</span >
                <span class="text-stage-text-dim" >·</span >
                <span >
                    @if ($match->phase === 'group')
                        Gruppenphase
                    @elseif ($match->phase === 'placement')
                        Platzierungsspiel
                    @else
                        KO &middot; @switch($match->ko_round)
                            @case(1) Finale @break
                            @case(2) Halbfinale @break
                            @case(4) Viertelfinale @break
                            @case(8) Achtelfinale @break
                            @default Runde {{ $match->ko_round }}
                        @endswitch
                    @endif
                </span >
            </div >
            <span class="badge badge-{{ str_replace('_', '-', $match->status) }}" >{{ $match->status }}</span >
        </div >

        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 px-5 py-7 lg:gap-8 lg:px-8 lg:py-10" >
            <div class="flex flex-col gap-2 text-left" >
                <span class="font-label text-red-corner-bright" >Red Corner</span >
                <span class="team-tag" >
                    @if ($home)
                        <span class="font-display text-stage-text text-[clamp(1.5rem,5vw,2.5rem)]" >{{ $home->name }}</span >
                    @else
                        <span class="font-display text-stage-text-dim text-[clamp(1.5rem,5vw,2.5rem)]" >Sieger Vorrunde</span >
                    @endif
                </span >
            </div >
            <div aria-hidden="true" class="face-off-divider h-20 w-px self-stretch" ></div >
            <div class="flex flex-col gap-2 text-right" >
                <span class="font-label text-blue-corner-bright" >Blue Corner</span >
                <span class="team-tag justify-end" >
                    @if ($away)
                        <span class="font-display text-stage-text text-[clamp(1.5rem,5vw,2.5rem)]" >{{ $away->name }}</span >
                    @else
                        <span class="font-display text-stage-text-dim text-[clamp(1.5rem,5vw,2.5rem)]" >Sieger Vorrunde</span >
                    @endif
                </span >
            </div >
        </div >
    </header >

    {{-- ───────── PRE_ENTRY ─────────
         No inputs here on purpose — Würfe & Strafe are entered LIVE during
         the active phase. Pre-entry exists only as a "Teams, an den Tisch"
         signal between matches. KO matches whose opponent is still in play
         render a "wartet auf Vorrunde" state instead. --}}
    @if (in_array($match->status, ['pending', 'pre_entry'], true))
        @php $teamsReady = $match->home_team_id !== null && $match->away_team_id !== null; @endphp
        <section class="space-y-6" >
            @if ($teamsReady)
                <div class="rounded-lg face-off-bg px-6 py-10 text-center lg:px-10 lg:py-14" >
                    <span class="font-label text-trophy-gold" >Bereitmachen</span >
                    <h2 class="mt-3 font-display text-stage-text text-[clamp(1.75rem,5vw,2.75rem)]" >
                        Teams an den Tisch
                    </h2 >
                    <p class="mt-3 text-sm text-stage-text-muted lg:text-base" >
                        Würfe und Strafbecher werden während des laufenden Spiels gezählt.
                    </p >
                </div >

                <button wire:click="startMatch"
                        class="w-full rounded-lg bg-stage-text px-5 py-5 text-lg font-bold tracking-wide text-stage-bg hover:opacity-90 active:scale-[0.99] transition" >
                    Spiel starten
                </button >
            @else
                <div class="rounded-lg border border-stage-line bg-stage-surface px-6 py-10 text-center lg:px-10 lg:py-14" >
                    <span class="font-label text-stage-text-dim" >Noch nicht spielbereit</span >
                    <h2 class="mt-3 font-display text-stage-text text-[clamp(1.5rem,4vw,2.25rem)]" >
                        Wartet auf Vorrunde
                    </h2 >
                    <p class="mt-3 max-w-md mx-auto text-sm text-stage-text-muted lg:text-base" >
                        @if ($match->home_team_id === null && $match->away_team_id === null)
                            Beide Vorrundenspiele müssen noch beendet werden.
                        @else
                            Das Vorrundenspiel der Gegenseite läuft noch. Sobald der Sieger feststeht, kann es hier weitergehen.
                        @endif
                    </p >
                </div >
            @endif
        </section >
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
                 x-destroy="clearInterval(handle)" >

            <div class="flex flex-col items-center gap-3 px-6 pt-12 pb-8 lg:pt-16 lg:pb-12" >
                <span class="live-marker" >Live</span >
                <div class="font-numeric text-[clamp(5rem,18vw,9rem)] font-bold leading-none tracking-tight"
                     :class="timerClass" >
                    <span x-text="display" ></span >
                </div >
                <span class="font-label text-stage-text-dim" >Verbleibende Zeit</span >
            </div >

            <div class="grid grid-cols-1 border-t border-stage-line md:grid-cols-2" >
                @foreach ([
                    'home' => ['team' => $home, 'throws' => 'homeThrows', 'penalty' => 'homePenalty', 'corner' => 'Red', 'tint' => 'red'],
                    'away' => ['team' => $away, 'throws' => 'awayThrows', 'penalty' => 'awayPenalty', 'corner' => 'Blue', 'tint' => 'blue'],
                ] as $side => $cfg)
                    <div @class([
                        'space-y-4 px-5 py-5 lg:px-7 lg:py-6',
                        'border-t border-stage-line md:border-t-0 md:border-l' => $side === 'away',
                    ])>
                        <div class="flex items-center justify-between gap-3" >
                            <span
                                class="font-label text-{{ $cfg['tint'] }}-corner-bright" >{{ $cfg['corner'] }} &mdash; {{ $cfg['team']?->name }}</span >
                        </div >

                        @foreach (array_filter([
                            $countThrows ? ['label' => 'Würfe', 'field' => $cfg['throws']] : null,
                            ['label' => 'Strafe', 'field' => $cfg['penalty']],
                        ]) as $row)
                            <div class="flex items-center justify-between gap-3" >
                                <label class="font-label text-stage-text-dim" >{{ $row['label'] }}</label >
                                <div class="flex items-center gap-2" >
                                    <button wire:click="adjust('{{ $row['field'] }}', -1)" type="button"
                                            class="h-11 w-11 rounded-md bg-stage-bg/60 text-xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition" >
                                        &minus;
                                    </button >
                                    <input wire:model.live="{{ $row['field'] }}" name="{{ $row['field'] }}" type="number" min="0"
                                           inputmode="numeric"
                                           class="h-11 w-16 rounded-md border border-stage-line bg-stage-bg/60 text-center font-numeric text-lg font-semibold text-stage-text focus:border-stage-line-strong" >
                                    <button wire:click="adjust('{{ $row['field'] }}', 1)" type="button"
                                            class="h-11 w-11 rounded-md bg-stage-bg/60 text-xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition" >
                                        +
                                    </button >
                                </div >
                            </div >
                        @endforeach
                    </div >
                @endforeach
            </div >
        </section >

        <flux:modal.trigger name="confirm-end-round" >
            <button type="button"
                    class="w-full rounded-lg border border-status-danger bg-status-danger-soft px-5 py-5 text-lg font-bold tracking-wide text-status-danger hover:bg-stage-surface-2 active:scale-[0.99] transition" >
                Runde beenden
            </button >
        </flux:modal.trigger >

        <flux:modal name="confirm-end-round" class="md:w-104" >
            <div class="space-y-6" >
                <div >
                    <flux:heading size="lg" >Runde beenden?</flux:heading >
                    <flux:text class="mt-2" >
                        @if ($countThrows)
                            Sobald die Runde endet, geht es weiter zur Becher-Eingabe. Würfe und Strafe lassen sich danach nicht mehr
                            ändern.
                        @else
                            Sobald die Runde endet, geht es weiter zur Becher-Eingabe. Die Strafbecher lassen sich danach nicht mehr ändern.
                        @endif
                    </flux:text >
                </div >
                <div class="flex gap-2" >
                    <flux:spacer />
                    <flux:modal.close >
                        <flux:button variant="ghost" >Abbrechen</flux:button >
                    </flux:modal.close >
                    <flux:button wire:click="endTimer" variant="danger" data-test="confirm-end-round" >
                        Ja, Runde beenden
                    </flux:button >
                </div >
            </div >
        </flux:modal >
    @endif

    {{-- ───────── SCORING ───────── --}}
    @if ($match->status === 'scoring')
        <section class="space-y-6" >
            <div class="flex items-baseline justify-between" >
                <h2 class="font-label text-stage-text-muted" >Getroffene Becher eintragen</h2 >
                <span class="text-xs text-stage-text-dim" >Hauptmetrik · entscheidet Sieg</span >
            </div >

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2" >
                @foreach ([
                    'home' => ['team' => $home, 'field' => 'homeCups', 'corner' => 'Red', 'tint' => 'red'],
                    'away' => ['team' => $away, 'field' => 'awayCups', 'corner' => 'Blue', 'tint' => 'blue'],
                ] as $side => $cfg)
                    <div class="space-y-4 rounded-lg p-6"
                         style="background: linear-gradient(180deg, var(--color-{{ $cfg['tint'] }}-corner-soft) 0%, transparent 100%), var(--color-stage-surface);" >
                        <div class="flex items-baseline justify-between" >
                            <span class="font-label text-{{ $cfg['tint'] }}-corner-bright" >{{ $cfg['corner'] }} Corner</span >
                            <span class="team-tag" >
                                <span class="font-display text-lg" >{{ $cfg['team']?->name }}</span >
                            </span >
                        </div >

                        <div class="flex items-center justify-center gap-4 pt-2" >
                            <button wire:click="adjust('{{ $cfg['field'] }}', -1)" type="button"
                                    class="h-16 w-16 rounded-lg bg-stage-bg text-3xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition" >
                                &minus;
                            </button >
                            <input wire:model.live="{{ $cfg['field'] }}" name="{{ $cfg['field'] }}" type="number" min="0" max="30"
                                   inputmode="numeric"
                                   class="h-24 w-28 rounded-lg border border-stage-line bg-stage-bg text-center font-numeric text-5xl font-bold text-stage-text focus:border-stage-line-strong" >
                            <button wire:click="adjust('{{ $cfg['field'] }}', 1)" type="button"
                                    class="h-16 w-16 rounded-lg bg-stage-bg text-3xl font-semibold text-stage-text hover:bg-stage-surface-2 active:scale-95 transition" >
                                +
                            </button >
                        </div >
                    </div >
                @endforeach
            </div >

            @error('homeCups') <p class="text-sm text-status-danger" >{{ $message }}</p > @enderror
            @error('awayCups') <p class="text-sm text-status-danger" >{{ $message }}</p > @enderror

            {{-- Sudden Death: tied KO match, referee picks the shoot-out winner. --}}
            @if ($this->needsSuddenDeath)
                <div class="rounded-lg border border-trophy-gold/40 bg-trophy-gold-soft p-6" >
                    <div class="font-label flex items-center gap-3 text-trophy-gold" >
                        <span class="block h-px w-12 bg-trophy-gold" ></span >
                        <span >Sudden Death</span >
                    </div >
                    <h3 class="mt-3 font-display text-xl text-stage-text lg:text-2xl" >Gleichstand — wer gewinnt das Stechen?</h3 >
                    <p class="mt-2 text-sm text-stage-text-muted" >
                        Schere, Stein, Papier um den Anwurf, kein Nachwurf — der erste Treffer gewinnt. Trage hier das siegreiche Team ein.
                    </p >

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2" >
                        @foreach ([
                            ['team' => $home, 'id' => $match->home_team_id, 'corner' => 'Red', 'tint' => 'red'],
                            ['team' => $away, 'id' => $match->away_team_id, 'corner' => 'Blue', 'tint' => 'blue'],
                        ] as $cfg)
                            <button type="button"
                                    wire:click="$set('suddenDeathWinner', {{ $cfg['id'] }})"
                                @class([
                                    'flex items-center justify-between gap-3 rounded-lg border px-5 py-4 text-left transition',
                                    'border-trophy-gold bg-stage-bg' => $suddenDeathWinner === $cfg['id'],
                                    'border-stage-line-strong bg-stage-bg/60 hover:border-stage-text' => $suddenDeathWinner !== $cfg['id'],
                                ])>
                                <span class="team-tag" >
                                    <span class="font-display text-lg text-stage-text" >{{ $cfg['team']?->name }}</span >
                                </span >
                                @if ($suddenDeathWinner === $cfg['id'])
                                    <flux:icon.check-circle class="size-6 text-trophy-gold" />
                                @else
                                    <span class="font-label text-{{ $cfg['tint'] }}-corner-bright" >{{ $cfg['corner'] }}</span >
                                @endif
                            </button >
                        @endforeach
                    </div >

                    @error('suddenDeathWinner') <p class="mt-3 text-sm text-status-danger" >{{ $message }}</p > @enderror
                </div >
            @endif

            <button wire:click="saveResult"
                    class="w-full rounded-lg bg-stage-text px-5 py-5 text-lg font-bold tracking-wide text-stage-bg hover:opacity-90 active:scale-[0.99] transition" >
                Ergebnis speichern
            </button >
        </section >
    @endif

    {{-- ───────── FINISHED ───────── --}}
    @if ($match->status === 'finished')
        @php
            $winner = $match->winnerTeam;
            $homeStat = $match->stats->firstWhere('team_id', $match->home_team_id);
            $awayStat = $match->stats->firstWhere('team_id', $match->away_team_id);
            $duration = $homeStat?->duration_seconds ?? $awayStat?->duration_seconds;
            $homeWin = $match->winner_team_id === $match->home_team_id;
            $decidedBySuddenDeath = $match->phase === 'ko'
                && ($match->tournament->ko_sudden_death ?? false)
                && ($homeStat?->cups_scored ?? 0) === ($awayStat?->cups_scored ?? 0);
        @endphp
        <section class="overflow-hidden rounded-lg border border-trophy-gold/40 bg-trophy-gold-soft" >
            <div class="px-6 py-10 lg:px-10 lg:py-14" >
                <div class="font-label flex items-center gap-3 text-trophy-gold" >
                    <span class="block h-px w-12 bg-trophy-gold" ></span >
                    <span >Sieger</span >
                </div >
                <h2 class="mt-4 font-display text-trophy-gold text-[clamp(2rem,7vw,4rem)]" >
                    {{ $winner?->name }}
                </h2 >
                <p class="mt-6 font-numeric text-[clamp(2rem,6vw,3.5rem)] font-bold text-stage-text" >
                    <span
                        class="@if($homeWin) text-trophy-gold @else text-stage-text-muted @endif" >{{ $homeStat?->cups_scored ?? 0 }}</span >
                    <span class="mx-3 text-stage-text-dim" >:</span >
                    <span
                        class="@if(! $homeWin) text-trophy-gold @else text-stage-text-muted @endif" >{{ $awayStat?->cups_scored ?? 0 }}</span >
                </p >
                @if ($decidedBySuddenDeath)
                    <p class="mt-4 font-label text-stage-text-dim" >Entschieden im Sudden Death</p >
                @endif
            </div >

            <dl @class([
                'grid border-t border-trophy-gold/30',
                'grid-cols-3' => $countThrows,
                'grid-cols-2' => ! $countThrows,
            ])>
                <div class="border-r border-trophy-gold/30 px-5 py-4 text-center lg:py-5" >
                    <dt class="font-label text-stage-text-dim" >Dauer</dt >
                    <dd class="mt-1 font-numeric text-lg font-semibold text-stage-text" >
                        {{ $duration ? sprintf('%02d:%02d', intdiv($duration, 60), $duration % 60) : '—' }}
                    </dd >
                </div >
                @if ($countThrows)
                    <div class="border-r border-trophy-gold/30 px-5 py-4 text-center lg:py-5" >
                        <dt class="font-label text-stage-text-dim" >Würfe</dt >
                        <dd class="mt-1 font-numeric text-lg font-semibold text-stage-text" >{{ $homeStat?->throws ?? 0 }}
                            : {{ $awayStat?->throws ?? 0 }}</dd >
                    </div >
                @endif
                <div class="px-5 py-4 text-center lg:py-5" >
                    <dt class="font-label text-stage-text-dim" >Strafe</dt >
                    <dd class="mt-1 font-numeric text-lg font-semibold text-stage-text" >{{ $homeStat?->penalty_cups ?? 0 }}
                        : {{ $awayStat?->penalty_cups ?? 0 }}</dd >
                </div >
            </dl >
        </section >

        <a href="{{ route('matches.index') }}" wire:navigate
           class="inline-flex items-center gap-2 rounded-md border border-stage-line-strong px-5 py-3 text-sm font-medium text-stage-text hover:bg-stage-surface transition" >
            <flux:icon.arrow-left class="size-4" />
            Zurück zur Match-Liste
        </a >
    @endif

    {{-- Cup-king rule: distribute the scored cups across each team's players. --}}
    @if ($match->tournament->determine_cup_king ?? false)
        <flux:modal name="distribute-cups" class="max-w-2xl" >
            <div class="space-y-6" >
                <div >
                    <flux:heading size="lg" >{{ __('Getroffene Becher verteilen') }}</flux:heading >
                    <flux:text
                        class="mt-2" >{{ __('Verteile die getroffenen Becher dieses Spiels auf die Spieler. Strafbecher müssen nicht aufgehen.') }}</flux:text >
                </div >

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2" >
                    @foreach ($this->distributionTeams as $team)
                        <div class="space-y-3 rounded-md bg-stage-surface px-4 py-3" >
                            <div class="flex items-center gap-2" >
                                <span class="font-label text-stage-text" >{{ $team->name }}</span >
                            </div >
                            @forelse ($team->members as $member)
                                <flux:input
                                    type="number"
                                    min="0"
                                    wire:model="cupDistribution.{{ $member->id }}"
                                    :label="$member->name"
                                />
                            @empty
                                <p class="text-sm text-stage-text-dim" >{{ __('Keine Teammitglieder benannt.') }}</p >
                            @endforelse
                        </div >
                    @endforeach
                </div >

                <div class="flex gap-2" >
                    <flux:spacer />
                    <flux:modal.close >
                        <flux:button variant="ghost" >{{ __('Schließen') }}</flux:button >
                    </flux:modal.close >
                    <flux:button wire:click="saveCupDistribution" variant="primary" data-test="save-cup-distribution-button" >
                        {{ __('Becher speichern') }}
                    </flux:button >
                </div >
            </div >
        </flux:modal >
    @endif
</div >
