<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Tournament;
use Illuminate\Support\Collection;

class StatisticsService
{
    /**
     * @return array{
     *   champion: ?array{name:string, color:?string},
     *   sharpest_shooter: ?array{team:string, rate:float, scored:int, throws:int},
     *   water_spitter: ?array{team:string, rate:float, scored:int, throws:int},
     *   blitz_win: ?array{team:string, duration:int, opponent:string},
     *   marathon: ?array{teams:array<int,string>, duration:int},
     *   cup_emperor: ?array{team:string, cups:int, opponent:string},
     *   penalty_magnet: ?array{team:string, penalty_cups:int},
     *   efficiency: ?array{team:string, rate:float},
     *   most_played: ?array{team:string, matches:int},
     *   total_cups: int,
     * }
     */
    public function summary(Tournament $tournament): array
    {
        $finishedMatches = $tournament->matches()
            ->where('status', 'finished')
            ->with(['stats.team', 'homeTeam', 'awayTeam', 'winnerTeam'])
            ->get();

        return [
            'champion' => $this->champion($tournament),
            'sharpest_shooter' => $this->shooterRate($finishedMatches, true),
            'water_spitter' => $this->shooterRate($finishedMatches, false),
            'blitz_win' => $this->blitzWin($finishedMatches),
            'marathon' => $this->marathon($finishedMatches),
            'cup_emperor' => $this->cupEmperor($finishedMatches),
            'penalty_magnet' => $this->penaltyMagnet($finishedMatches),
            'efficiency' => $this->efficiency($finishedMatches),
            'most_played' => $this->mostPlayed($finishedMatches),
            'total_cups' => (int) $finishedMatches->flatMap->stats->sum('cups_scored'),
        ];
    }

    private function champion(Tournament $tournament): ?array
    {
        if (! $tournament->isFinished()) {
            return null;
        }

        $final = $tournament->matches()
            ->where('phase', 'ko')
            ->where('ko_round', 1)
            ->where('status', 'finished')
            ->with('winnerTeam')
            ->first();

        if (! $final || ! $final->winnerTeam) {
            return null;
        }

        return [
            'name' => $final->winnerTeam->name,
            'color' => $final->winnerTeam->color,
        ];
    }

    private function shooterRate(Collection $matches, bool $highest): ?array
    {
        $accumulators = [];
        foreach ($matches as $match) {
            foreach ($match->stats as $stat) {
                $key = $stat->team_id;
                if (! isset($accumulators[$key])) {
                    $accumulators[$key] = ['name' => $stat->team?->name, 'scored' => 0, 'throws' => 0];
                }
                $accumulators[$key]['scored'] += $stat->cups_scored;
                $accumulators[$key]['throws'] += $stat->throws;
            }
        }

        $rows = collect($accumulators)
            ->filter(fn ($row) => $row['throws'] > 0)
            ->map(fn ($row) => $row + ['rate' => $row['scored'] / $row['throws']]);

        if ($rows->isEmpty()) {
            return null;
        }

        $row = $highest ? $rows->sortByDesc('rate')->first() : $rows->sortBy('rate')->first();

        return [
            'team' => $row['name'] ?? '—',
            'rate' => round($row['rate'] * 100, 1),
            'scored' => $row['scored'],
            'throws' => $row['throws'],
        ];
    }

    private function blitzWin(Collection $matches): ?array
    {
        $winnerMatches = $matches->filter(fn (GameMatch $m) => $m->phase === 'group' && $m->winner_team_id);

        $blitz = null;
        foreach ($winnerMatches as $match) {
            $stat = $match->stats->firstWhere('team_id', $match->winner_team_id);
            $duration = $stat?->duration_seconds;
            if ($duration === null) {
                continue;
            }
            if ($blitz === null || $duration < $blitz['duration']) {
                $loser = $match->winner_team_id === $match->home_team_id ? $match->awayTeam : $match->homeTeam;
                $blitz = [
                    'team' => $match->winnerTeam?->name ?? '—',
                    'duration' => (int) $duration,
                    'opponent' => $loser?->name ?? '—',
                ];
            }
        }

        return $blitz;
    }

    private function marathon(Collection $matches): ?array
    {
        $longest = null;
        foreach ($matches as $match) {
            $duration = $match->stats->max('duration_seconds');
            if ($duration === null) {
                continue;
            }
            if ($longest === null || $duration > $longest['duration']) {
                $longest = [
                    'teams' => array_filter([$match->homeTeam?->name, $match->awayTeam?->name]),
                    'duration' => (int) $duration,
                ];
            }
        }

        return $longest;
    }

    private function cupEmperor(Collection $matches): ?array
    {
        $best = null;
        foreach ($matches as $match) {
            foreach ($match->stats as $stat) {
                if ($best === null || $stat->cups_scored > $best['cups']) {
                    $other = $stat->team_id === $match->home_team_id ? $match->awayTeam : $match->homeTeam;
                    $best = [
                        'team' => $stat->team?->name ?? '—',
                        'cups' => (int) $stat->cups_scored,
                        'opponent' => $other?->name ?? '—',
                    ];
                }
            }
        }

        return $best;
    }

    private function penaltyMagnet(Collection $matches): ?array
    {
        $totals = [];
        foreach ($matches as $match) {
            foreach ($match->stats as $stat) {
                $totals[$stat->team_id] = [
                    'name' => $stat->team?->name,
                    'penalty_cups' => ($totals[$stat->team_id]['penalty_cups'] ?? 0) + $stat->penalty_cups,
                ];
            }
        }

        $row = collect($totals)->sortByDesc('penalty_cups')->first();
        if (! $row) {
            return null;
        }

        return [
            'team' => $row['name'] ?? '—',
            'penalty_cups' => (int) $row['penalty_cups'],
        ];
    }

    private function efficiency(Collection $matches): ?array
    {
        $totals = [];
        foreach ($matches as $match) {
            foreach ($match->stats as $stat) {
                $key = $stat->team_id;
                if (! isset($totals[$key])) {
                    $totals[$key] = ['name' => $stat->team?->name, 'scored' => 0, 'penalty' => 0, 'throws' => 0];
                }
                $totals[$key]['scored'] += $stat->cups_scored;
                $totals[$key]['penalty'] += $stat->penalty_cups;
                $totals[$key]['throws'] += $stat->throws;
            }
        }

        $rows = collect($totals)
            ->filter(fn ($row) => $row['throws'] > 0)
            ->map(fn ($row) => $row + ['rate' => (($row['scored'] - $row['penalty']) / $row['throws']) * 100]);

        $row = $rows->sortByDesc('rate')->first();
        if (! $row) {
            return null;
        }

        return [
            'team' => $row['name'] ?? '—',
            'rate' => round($row['rate'], 1),
        ];
    }

    private function mostPlayed(Collection $matches): ?array
    {
        $counts = [];
        foreach ($matches as $match) {
            foreach ([$match->homeTeam, $match->awayTeam] as $team) {
                if (! $team) {
                    continue;
                }
                $counts[$team->id] = [
                    'name' => $team->name,
                    'matches' => ($counts[$team->id]['matches'] ?? 0) + 1,
                ];
            }
        }

        $row = collect($counts)->sortByDesc('matches')->first();
        if (! $row) {
            return null;
        }

        return [
            'team' => $row['name'] ?? '—',
            'matches' => (int) $row['matches'],
        ];
    }
}
