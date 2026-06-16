<?php

use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public ?int $tournamentId = null;

    public function mount(): void
    {
        $this->tournamentId = Tournament::query()->latest()->value('id');
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return $this->tournamentId
            ? Tournament::with(['teams', 'tables', 'groups', 'matches'])->find($this->tournamentId)
            : null;
    }

    #[Computed]
    public function activeMatches()
    {
        if (! $this->tournamentId) {
            return collect();
        }

        return \App\Models\GameMatch::where('tournament_id', $this->tournamentId)
            ->whereIn('status', ['active', 'pre_entry', 'scoring'])
            ->with(['homeTeam', 'awayTeam', 'table'])
            ->get();
    }

    #[Computed]
    public function totals(): array
    {
        if (! $this->tournamentId) {
            return ['matches' => 0, 'finished' => 0, 'pending' => 0];
        }

        $matches = \App\Models\GameMatch::where('tournament_id', $this->tournamentId);

        return [
            'matches' => (clone $matches)->count(),
            'finished' => (clone $matches)->where('status', 'finished')->count(),
            'pending' => (clone $matches)->where('status', 'pending')->count(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-6xl space-y-10 p-4 lg:p-6" wire:poll.15s>
    @php
        $tournament = $this->tournament;
        $totals = $this->totals;
        $active = $this->activeMatches;
        $phaseLabels = [
            'setup' => 'Vorbereitung',
            'group' => 'Gruppenphase',
            'placement' => 'Platzierungsspiele',
            'ko' => 'KO-Phase',
            'finished' => 'Beendet',
        ];
    @endphp

    {{-- Title block --}}
    <header class="flex flex-col gap-5">
        @if ($tournament)
            <div class="font-label flex items-center gap-3 text-trophy-gold">
                <span class="block h-px w-12 bg-trophy-gold"></span>
                <span>{{ $phaseLabels[$tournament->status] ?? $tournament->status }}</span>
            </div>
        @endif

        <div class="flex flex-wrap items-end justify-between gap-4">
            <h1 class="font-display text-stage-text text-[clamp(2rem,5vw,3.5rem)]">
                {{ $tournament?->name ?? __('Kein Turnier vorhanden') }}
            </h1>
            @if ($tournament)
                <a href="{{ route('tournament.public', $tournament->public_token) }}" target="_blank"
                   class="inline-flex items-center gap-2 rounded-md border border-stage-line-strong px-4 py-2 text-sm font-medium text-stage-text hover:bg-stage-surface transition">
                    <flux:icon.arrow-top-right-on-square class="size-4" />
                    Public-Link
                </a>
            @endif
        </div>

        @if ($tournament)
            <dl class="flex flex-wrap items-baseline gap-x-10 gap-y-3 pt-3 text-sm">
                <div class="flex items-baseline gap-2">
                    <dt class="font-label text-stage-text-dim">Matches</dt>
                    <dd class="font-numeric text-2xl font-semibold text-stage-text">
                        {{ $totals['finished'] }}<span class="text-stage-text-dim">/{{ $totals['matches'] }}</span>
                    </dd>
                </div>
                <div class="flex items-baseline gap-2">
                    <dt class="font-label text-stage-text-dim">Offen</dt>
                    <dd class="font-numeric text-2xl font-semibold text-stage-text">{{ $totals['pending'] }}</dd>
                </div>
                <div class="flex items-baseline gap-2">
                    <dt class="font-label text-stage-text-dim">Live</dt>
                    <dd class="font-numeric text-2xl font-semibold @if($active->count() > 0) text-trophy-gold @else text-stage-text-muted @endif">{{ $active->count() }}</dd>
                </div>
            </dl>
        @else
            <p class="text-stage-text-muted">Lege als Admin im Setup ein Turnier an.</p>
        @endif
    </header>

    {{-- Live matches --}}
    @if ($active->isNotEmpty())
        <section class="space-y-5">
            <h2 class="live-marker">Live · jetzt am Tisch</h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($active as $match)
                    @php $home = $match->homeTeam; $away = $match->awayTeam; @endphp
                    <a href="{{ route('match.score', $match) }}" wire:navigate
                       class="relative overflow-hidden rounded-lg border border-stage-line live-glow face-off-bg transition hover:border-trophy-gold">
                        <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-3">
                            <span class="font-label text-stage-text-muted">{{ $match->table?->name }}</span>
                            <span class="badge badge-{{ str_replace('_', '-', $match->status) }}">{{ $match->status }}</span>
                        </div>
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 px-5 py-5">
                            <div class="flex items-center justify-start gap-2 min-w-0">
                                <span class="truncate font-display text-base text-stage-text">{{ $home?->name }}</span>
                            </div>
                            <span class="font-label text-stage-text-dim">vs</span>
                            <div class="flex items-center justify-end gap-2 min-w-0">
                                <span class="truncate font-display text-base text-stage-text">{{ $away?->name }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Leaderboard (typographic, no card wrap) --}}
    @if ($tournament)
        <section class="space-y-5">
            <h2 class="font-label text-stage-text-muted">Leaderboard</h2>
            <livewire:pages::leaderboard :tournament-id="$tournament->id" />
        </section>
    @endif
</div>
