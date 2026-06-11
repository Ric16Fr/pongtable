<?php

use App\Models\Tournament;
use App\Services\KoBracketService;
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
        return $this->tournamentId ? Tournament::with('groups.teams')->find($this->tournamentId) : null;
    }

    #[Computed]
    public function rows(): array
    {
        $tournament = $this->tournament;
        if (! $tournament) {
            return [];
        }

        $service = app(KoBracketService::class);
        $combined = collect();

        foreach ($tournament->groups as $group) {
            foreach ($service->groupStandings($group) as $team) {
                $combined->push([
                    'team' => $team,
                    'group' => $group->name,
                    'points' => $team->pivot->points,
                    'wins' => $team->pivot->wins,
                    'losses' => $team->pivot->losses,
                    'cups_scored' => $team->pivot->cups_scored_total,
                    'cups_conceded' => $team->pivot->cups_conceded_total,
                    'cup_diff' => $team->pivot->cups_scored_total - $team->pivot->cups_conceded_total,
                ]);
            }
        }

        $rows = $combined
            ->sortByDesc(fn ($r) => $r['points'] * 100000 + $r['cup_diff'] * 100 + $r['cups_scored'])
            ->values();

        return $this->applyPlacementResults($tournament, $rows)->all();
    }

    /**
     * Finished placement matches override the group-phase order: each
     * winner takes the better of the two slots its pairing occupies.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function applyPlacementResults(Tournament $tournament, $rows)
    {
        $placementMatches = $tournament->matches()
            ->where('phase', 'placement')
            ->where('status', 'finished')
            ->whereNotNull('winner_team_id')
            ->get(['home_team_id', 'away_team_id', 'winner_team_id']);

        foreach ($placementMatches as $match) {
            $loserId = $match->winner_team_id === $match->home_team_id
                ? $match->away_team_id
                : $match->home_team_id;

            $winnerIndex = $rows->search(fn ($r) => $r['team']->id === $match->winner_team_id);
            $loserIndex = $rows->search(fn ($r) => $r['team']->id === $loserId);

            if ($winnerIndex !== false && $loserIndex !== false && $loserIndex < $winnerIndex) {
                $winnerRow = $rows[$winnerIndex];
                $rows[$winnerIndex] = $rows[$loserIndex];
                $rows[$loserIndex] = $winnerRow;
            }
        }

        return $rows;
    }
}; ?>

<div wire:poll.15s>
    @if (empty($this->rows))
        <p class="text-sm text-stage-text-dim">Noch ruhig hier. Sobald der erste Becher fällt, sortiert sich's sofort.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="font-label text-stage-text-dim">
                        <th class="py-2 pr-3 text-left font-semibold">#</th>
                        <th class="py-2 pr-3 text-left font-semibold">Team</th>
                        <th class="py-2 pr-3 text-left font-semibold">Gruppe</th>
                        <th class="py-2 pr-3 text-right font-semibold">Pkt</th>
                        <th class="py-2 pr-3 text-right font-semibold">W</th>
                        <th class="py-2 pr-3 text-right font-semibold">L</th>
                        <th class="py-2 pr-3 text-right font-semibold">Cups</th>
                        <th class="py-2 pr-3 text-right font-semibold">+/&minus;</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->rows as $i => $row)
                        @php
                            $rank = $i + 1;
                            $isPodium = $rank <= 3;
                        @endphp
                        <tr class="border-t border-stage-line @if(! $isPodium && $rank > 6) text-stage-text-dim @elseif(! $isPodium) text-stage-text-muted @endif">
                            <td class="py-3 pr-3">
                                <span class="rank-chip" data-rank="{{ $rank }}">{{ $rank }}</span>
                            </td>
                            <td class="py-3 pr-3 font-medium">
                                <span class="team-tag">
                                    <span class="team-dot" @if($row['team']->color) style="background-color: {{ $row['team']->color }}" @endif></span>
                                    <span class="@if($rank === 1) font-display text-lg text-trophy-gold @elseif($isPodium) text-stage-text @endif">{{ $row['team']->name }}</span>
                                </span>
                            </td>
                            <td class="py-3 pr-3 text-stage-text-dim">{{ $row['group'] }}</td>
                            <td class="py-3 pr-3 text-right">
                                <span class="font-numeric text-base font-semibold @if($rank === 1) text-trophy-gold @else text-stage-text @endif">{{ $row['points'] }}</span>
                            </td>
                            <td class="py-3 pr-3 text-right">
                                <span class="font-numeric text-xs text-status-success">{{ $row['wins'] }}</span>
                            </td>
                            <td class="py-3 pr-3 text-right">
                                <span class="font-numeric text-xs text-stage-text-dim">{{ $row['losses'] }}</span>
                            </td>
                            <td class="py-3 pr-3 text-right">
                                <span class="font-numeric text-xs">{{ $row['cups_scored'] }}:{{ $row['cups_conceded'] }}</span>
                            </td>
                            <td class="py-3 pr-3 text-right">
                                <span class="font-numeric text-xs @if($row['cup_diff'] > 0) text-status-success @elseif($row['cup_diff'] < 0) text-status-danger @endif">
                                    {{ $row['cup_diff'] >= 0 ? '+'.$row['cup_diff'] : $row['cup_diff'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
