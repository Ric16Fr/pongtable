<?php

use App\Models\Tournament;
use App\Services\KoBracketService;
use Illuminate\Support\Pluralizer;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $tournamentId = null;

    public function mount(?int $tournamentId = null): void
    {
        $this->tournamentId = $tournamentId ?? Tournament::query()->latest()->value('id');
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        if (!$this->tournamentId) {
            return null;
        }

        return Tournament::with([
            'groups.teams',
            'groups.table',
            'matches' => fn($q) => $q->where('phase', 'ko')->orderBy('ko_round', 'desc')->orderBy('ko_position'),
            'matches.homeTeam',
            'matches.awayTeam',
            'matches.winnerTeam',
            'matches.stats',
            'matches.table',
        ])->find($this->tournamentId);
    }

    #[Computed]
    public function groupStandings(): array
    {
        $tournament = $this->tournament;
        if (!$tournament) {
            return [];
        }

        $service = app(KoBracketService::class);

        return $tournament->groups->map(fn($group) => [
            'group' => $group,
            'rows' => $service->groupStandings($group),
        ])->all();
    }

    #[Computed]
    public function koRounds(): array
    {
        $tournament = $this->tournament;
        if (!$tournament) {
            return [];
        }

        $matches = $tournament->matches->where('phase', 'ko');

        return $matches->groupBy('ko_round')
            ->sortKeysDesc()
            ->map(fn($matches, $round) => [
                'round' => (int)$round,
                'label' => $this->roundLabel((int)$round),
                'matches' => $matches->sortBy('ko_position')->values(),
            ])
            ->values()
            ->all();
    }

    private function roundLabel(int $round): string
    {
        return match ($round) {
            1 => 'Finale',
            2 => 'Halbfinale',
            4 => 'Viertelfinale',
            8 => 'Achtelfinale',
            default => "Runde {$round}",
        };
    }

    #[Computed]
    public function activeMatches()
    {
        if (!$this->tournamentId) {
            return collect();
        }

        return \App\Models\GameMatch::where('tournament_id', $this->tournamentId)
            ->whereIn('status', ['active', 'pre_entry', 'scoring'])
            ->with(['homeTeam', 'awayTeam', 'table'])
            ->get();
    }
}; ?>

<div wire:poll.10s class="space-y-14" >
    @php
        $t = $this->tournament;
    @endphp
    @if (! $t)
        <p class="font-label text-stage-text-dim" >Hier wird gleich gespielt. Sobald ein Admin loslegt.</p >
    @else

        {{-- LIVE MATCHES: each gets the full face-off treatment --}}
        @if ($this->activeMatches->isNotEmpty())
            @php $liveCount = $this->activeMatches->count(); @endphp
            <section class="space-y-5" >
                <div class="flex items-baseline justify-between" >
                    <h2 class="live-marker" >Live</h2 >
                    <span class="font-label text-stage-text-dim" >
                        <span class="font-numeric text-stage-text" >{{ $liveCount }}</span >
                        {{ $liveCount === 1 ? 'Spiel läuft jetzt' : 'Spiele laufen jetzt' }}
                    </span >
                </div >

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2" >
                    @foreach ($this->activeMatches as $match)
                        @php $home = $match->homeTeam; $away = $match->awayTeam; @endphp
                        <article class="relative overflow-hidden rounded-lg border border-stage-line live-glow face-off-bg" >
                            <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-3" >
                                <span class="font-label text-stage-text-muted" >{{ $match->table?->name }}</span >
                                <span class="badge badge-{{ str_replace('_', '-', $match->status) }}" >{{ $match->status }}</span >
                            </div >
                            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 px-5 py-6 lg:gap-6 lg:py-8" >
                                <div class="flex flex-col gap-2 text-left" >
                                    <span class="font-label text-red-corner-bright" >Red Corner</span >
                                    <span class="team-tag" >
                                        <span class="team-dot"
                                              @if($home?->color) style="background-color: {{ $home->color }}" @endif></span >
                                        <span class="font-display text-stage-text text-xl lg:text-2xl" >{{ $home?->name }}</span >
                                    </span >
                                </div >
                                <div aria-hidden="true" class="face-off-divider h-16 w-px self-stretch" ></div >
                                <div class="flex flex-col gap-2 text-right" >
                                    <span class="font-label text-blue-corner-bright" >Blue Corner</span >
                                    <span class="team-tag justify-end" >
                                        <span class="font-display text-stage-text text-xl lg:text-2xl" >{{ $away?->name }}</span >
                                        <span class="team-dot"
                                              @if($away?->color) style="background-color: {{ $away->color }}" @endif></span >
                                    </span >
                                </div >
                            </div >
                        </article >
                    @endforeach
                </div >
            </section >
        @endif

        {{-- KO BRACKET: calc-aligned columns with connectors --}}
        @if (! empty($this->koRounds))
            <section class="space-y-6" >
                <h2 class="font-label text-stage-text-muted" >KO-Bracket</h2 >
                <div class="ko-grid" >
                    @foreach ($this->koRounds as $i => $round)
                        <div class="ko-round" data-round-index="{{ $i }}" >
                            <div class="ko-round-label" data-round="{{ $round['round'] }}" >{{ $round['label'] }}</div >
                            <div class="ko-matches" >
                                @foreach ($round['matches'] as $match)
                                    @php
                                        $home = $match->stats->firstWhere('team_id', $match->home_team_id);
                                        $away = $match->stats->firstWhere('team_id', $match->away_team_id);
                                        $isLive = in_array($match->status, ['active', 'pre_entry', 'scoring'], true);
                                        $homeWin = $match->winner_team_id === $match->home_team_id;
                                        $awayWin = $match->winner_team_id === $match->away_team_id;
                                    @endphp
                                    <div class="ko-match" data-status="{{ $match->status }}" data-live="{{ $isLive ? 'true' : 'false' }}" >
                                        @if ($isLive)
                                            <div class="ko-match-meta" >
                                                <span class="live-marker" >Live</span >
                                                <span >{{ $match->table?->name }}</span >
                                            </div >
                                        @endif

                                        <div class="ko-team"
                                             @if($match->winner_team_id) data-winner="{{ $homeWin ? 'true' : 'false' }}" @endif>
                                            <span class="ko-team-name" >
                                                <span class="team-dot"
                                                      @if($match->homeTeam?->color) style="background-color: {{ $match->homeTeam->color }}" @endif></span >
                                                <span >{{ $match->homeTeam?->name ?? '—' }}</span >
                                            </span >
                                            <span class="ko-team-score" >{{ $home?->cups_scored ?? '–' }}</span >
                                        </div >

                                        <div class="ko-team"
                                             @if($match->winner_team_id) data-winner="{{ $awayWin ? 'true' : 'false' }}" @endif>
                                            <span class="ko-team-name" >
                                                <span class="team-dot"
                                                      @if($match->awayTeam?->color) style="background-color: {{ $match->awayTeam->color }}" @endif></span >
                                                <span >{{ $match->awayTeam?->name ?? '—' }}</span >
                                            </span >
                                            <span class="ko-team-score" >{{ $away?->cups_scored ?? '–' }}</span >
                                        </div >
                                    </div >
                                @endforeach
                            </div >
                        </div >
                    @endforeach
                </div >
            </section >
        @endif

        {{-- GROUP STANDINGS: typographic blocks, no card grid --}}
        @if ($t->groups->isNotEmpty())
            <section class="space-y-6" >
                <h2 class="font-label text-stage-text-muted" >Gruppenphase</h2 >
                <div class="grid grid-cols-1 gap-x-10 gap-y-10 md:grid-cols-2" >
                    @foreach ($this->groupStandings as $bucket)
                        <div class="flex flex-col gap-4" >
                            <div class="flex items-baseline justify-between border-b border-stage-line pb-3" >
                                <h3 class="font-display text-stage-text text-2xl" >{{ $bucket['group']->name }}</h3 >
                                <span class="font-label text-stage-text-dim" >{{ $bucket['group']->table?->name }}</span >
                            </div >

                            <table class="w-full text-sm" >
                                <thead >
                                <tr class="font-label text-stage-text-dim" >
                                    <th class="py-1 pr-2 text-left font-semibold" >#</th >
                                    <th class="py-1 pr-2 text-left font-semibold" >Team</th >
                                    <th class="py-1 pr-2 text-right font-semibold" >Pkt</th >
                                    <th class="py-1 pr-2 text-right font-semibold" >W&ndash;L</th >
                                    <th class="py-1 pr-2 text-right font-semibold" >+/&minus;</th >
                                </tr >
                                </thead >
                                <tbody >
                                @foreach ($bucket['rows'] as $i => $team)
                                    @php
                                        $diff = ($team->pivot->cups_scored_total ?? 0) - ($team->pivot->cups_conceded_total ?? 0);
                                        $podium = $i < 2;
                                    @endphp
                                    <tr class="border-t border-stage-line @if(! $podium) text-stage-text-muted @endif" >
                                        <td class="py-2.5 pr-2" >
                                            <span class="rank-chip" data-rank="{{ $i + 1 }}" >{{ $i + 1 }}</span >
                                        </td >
                                        <td class="py-2.5 pr-2 font-medium" >
                                                <span class="team-tag" >
                                                    <span class="team-dot"
                                                          @if($team->color) style="background-color: {{ $team->color }}" @endif></span >
                                                    <span >{{ $team->name }}</span >
                                                </span >
                                        </td >
                                        <td class="py-2.5 pr-2 text-right" >
                                            <span class="font-numeric font-semibold text-stage-text" >{{ $team->pivot->points }}</span >
                                        </td >
                                        <td class="py-2.5 pr-2 text-right" >
                                            <span
                                                class="font-numeric text-xs" >{{ $team->pivot->wins }}&ndash;{{ $team->pivot->losses }}</span >
                                        </td >
                                        <td class="py-2.5 pr-2 text-right" >
                                                <span
                                                    class="font-numeric text-xs @if($diff > 0) text-status-success @elseif($diff < 0) text-status-danger @endif" >
                                                    {{ $diff >= 0 ? '+'.$diff : $diff }}
                                                </span >
                                        </td >
                                    </tr >
                                @endforeach
                                </tbody >
                            </table >
                        </div >
                    @endforeach
                </div >
            </section >
        @endif
    @endif
</div >
