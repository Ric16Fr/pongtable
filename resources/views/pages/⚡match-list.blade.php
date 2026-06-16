<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Matches')] class extends Component {
    public ?int $tournamentId = null;

    public string $filter = 'all';

    public string $tableFilter = 'all';

    public function mount(): void
    {
        $this->tournamentId = Tournament::query()->latest()->value('id');
    }

    #[Computed]
    public function tables()
    {
        if (! $this->tournamentId) {
            return collect();
        }

        return Table::query()
            ->where('tournament_id', $this->tournamentId)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function matches()
    {
        if (! $this->tournamentId) {
            return collect();
        }

        $query = GameMatch::query()
            ->where('tournament_id', $this->tournamentId)
            ->with(['homeTeam', 'awayTeam', 'table', 'group']);

        if ($this->filter === 'open') {
            $query->whereIn('status', ['pending', 'pre_entry']);
        } elseif ($this->filter === 'live') {
            $query->whereIn('status', ['active', 'scoring']);
        } elseif ($this->filter === 'finished') {
            $query->where('status', 'finished');
        }

        if ($this->tableFilter !== 'all') {
            $query->where('table_id', $this->tableFilter);
        }

        return $query
            ->orderByRaw("CASE status
                WHEN 'active' THEN 1
                WHEN 'scoring' THEN 2
                WHEN 'pre_entry' THEN 3
                WHEN 'pending' THEN 4
                WHEN 'finished' THEN 5
                ELSE 6 END")
            ->orderBy('id')
            ->get();
    }
}; ?>

<div class="mx-auto w-full max-w-5xl space-y-8 p-4 lg:p-6" wire:poll.5s>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl text-stage-text lg:text-4xl">Matches</h1>
            <p class="font-label mt-2 text-stage-text-dim">{{ $this->matches->count() }} {{ Str::plural('Spiel', $this->matches->count()) }} · sortiert nach Status</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if ($this->tables->isNotEmpty())
                <div class="flex flex-wrap gap-1 rounded-lg bg-stage-surface p-1">
                    <button wire:click="$set('tableFilter', 'all')" type="button"
                            class="rounded-md px-4 py-2 text-sm font-semibold transition
                                   @if($tableFilter === 'all')
                                       bg-stage-text text-stage-bg
                                   @else
                                       text-stage-text-muted hover:text-stage-text
                                   @endif">
                        Alle Tische
                    </button>
                    @foreach ($this->tables as $table)
                        <button wire:click="$set('tableFilter', '{{ $table->id }}')" type="button"
                                class="rounded-md px-4 py-2 text-sm font-semibold transition
                                       @if((string) $tableFilter === (string) $table->id)
                                           bg-stage-text text-stage-bg
                                       @else
                                           text-stage-text-muted hover:text-stage-text
                                       @endif">
                            {{ $table->name }}
                        </button>
                    @endforeach
                </div>
            @endif
            <div class="flex flex-wrap gap-1 rounded-lg bg-stage-surface p-1">
                @foreach ([
                    'all' => 'Alle',
                    'live' => 'Live',
                    'open' => 'Offen',
                   /** 'finished' => 'Beendet', (excluded for now, why should I filter for endet matches) **/
                ] as $key => $label)
                    <button wire:click="$set('filter', '{{ $key }}')" type="button"
                            class="rounded-md px-4 py-2 text-sm font-semibold transition
                                   @if($filter === $key)
                                       bg-stage-text text-stage-bg
                                   @else
                                       text-stage-text-muted hover:text-stage-text
                                   @endif">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </header>

    @if ($this->matches->isEmpty())
        <div class="rounded-lg border border-stage-line bg-stage-surface px-6 py-12 text-center">
            <p class="font-label text-stage-text-muted">Keine Matches in dieser Ansicht</p>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('tournament.setup') }}" class="mt-4 inline-flex items-center gap-2 rounded-md border border-stage-line-strong px-4 py-2 text-sm font-medium text-stage-text hover:bg-stage-surface-2 transition">
                    Setup öffnen
                </a>
            @endif
        </div>
    @else
        <div class="-mx-2 divide-y divide-stage-line">
            @foreach ($this->matches as $match)
                @php
                    $teamsReady = $match->home_team_id !== null && $match->away_team_id !== null;
                    $clickable = $match->status !== 'finished' && $teamsReady;
                    $isLive = in_array($match->status, ['active', 'scoring'], true);
                    $homeStat = $match->stats->firstWhere('team_id', $match->home_team_id);
                    $awayStat = $match->stats->firstWhere('team_id', $match->away_team_id);
                    $homeWin = $match->winner_team_id === $match->home_team_id;
                    $awayWin = $match->winner_team_id === $match->away_team_id;
                @endphp
                <a @if($clickable) href="{{ route('match.score', $match) }}" wire:navigate @endif
                   class="group relative grid grid-cols-[7rem_1fr_auto] items-center gap-4 px-3 py-4 lg:gap-6 lg:px-4
                          @if($clickable) cursor-pointer hover:bg-stage-surface transition @else cursor-default opacity-75 @endif
                          @if($isLive) bg-stage-surface @endif">

                    {{-- Status column --}}
                    <div class="flex flex-col gap-1">
                        <span class="badge badge-{{ str_replace('_', '-', $match->status) }}">{{ $match->status }}</span>
                        <span class="text-xs text-stage-text-dim">
                            {{ $match->table?->name }}
                            @if ($match->phase === 'group')
                                · {{ $match->group?->name }}
                            @elseif ($match->phase === 'placement')
                                · Platzierung
                            @else
                                · KO @if($match->ko_round) R{{ $match->ko_round }} @endif
                            @endif
                        </span>
                    </div>

                    {{-- Two-corner face-off --}}
                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 min-w-0 lg:gap-5">
                        <div class="flex items-center justify-start gap-2 min-w-0">
                            <span class="truncate font-display text-base lg:text-lg @if($match->status === 'finished' && $homeWin) text-trophy-gold @elseif($match->status === 'finished') text-stage-text-dim @elseif(! $match->homeTeam) text-stage-text-dim italic @endif">
                                {{ $match->homeTeam?->name ?? 'Sieger Vorrunde' }}
                            </span>
                        </div>

                        @if ($match->status === 'finished')
                            <span class="font-numeric whitespace-nowrap text-sm text-stage-text">
                                <span class="@if($homeWin) text-trophy-gold font-bold @endif">{{ $homeStat?->cups_scored ?? 0 }}</span>
                                <span class="mx-1 text-stage-text-dim">:</span>
                                <span class="@if($awayWin) text-trophy-gold font-bold @endif">{{ $awayStat?->cups_scored ?? 0 }}</span>
                            </span>
                        @else
                            <span class="font-label text-stage-text-dim">vs</span>
                        @endif

                        <div class="flex items-center justify-end gap-2 min-w-0">
                            <span class="truncate font-display text-base lg:text-lg @if($match->status === 'finished' && $awayWin) text-trophy-gold @elseif($match->status === 'finished') text-stage-text-dim @elseif(! $match->awayTeam) text-stage-text-dim italic @endif">
                                {{ $match->awayTeam?->name ?? 'Sieger Vorrunde' }}
                            </span>
                        </div>
                    </div>

                    {{-- Action affordance --}}
                    <div class="flex items-center justify-end">
                        @if ($clickable)
                            <flux:icon.chevron-right class="size-5 text-stage-text-dim group-hover:text-stage-text transition" />
                        @elseif (! $teamsReady && $match->status !== 'finished')
                            <span class="font-label text-stage-text-dim">wartet</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
