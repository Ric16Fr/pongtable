<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchMemberCup;
use App\Models\Tournament;
use Illuminate\Support\Collection;

class StatisticsService
{
    /**
     * @return array{
     *   champion: ?array{name:string},
     *   sharpest_shooter: ?array{team:string, rate:float, scored:int, throws:int},
     *   water_spitter: ?array{team:string, rate:float, scored:int, throws:int},
     *   blitz_win: ?array{team:string, duration:int, opponent:string},
     *   marathon: ?array{teams:array<int,string>, duration:int},
     *   nail_biter: ?array{teams:array<int,string>, score:string, diff:int},
     *   penalty_magnet: ?array{team:string, penalty_cups:int},
     *   efficiency: ?array{team:string, rate:float},
     *   schluck_olymp: ?array{team:string, cups:int},
     *   cup_king: ?array{name:string, team:?string, cups:int},
     *   total_cups: int,
     * }
     */
    public function summary(Tournament $tournament): array
    {
        $finishedMatches = $tournament->matches()
            ->where('status', 'finished')
            ->with(['stats.team', 'homeTeam', 'awayTeam', 'winnerTeam'])
            ->get();

        $countThrows = $tournament->count_throws ?? true;

        return [
            'champion' => $this->champion($tournament),
            'sharpest_shooter' => $countThrows ? $this->shooterRate($finishedMatches, true) : null,
            'water_spitter' => $countThrows ? $this->shooterRate($finishedMatches, false) : null,
            'blitz_win' => $this->blitzWin($finishedMatches),
            'marathon' => $this->marathon($finishedMatches),
            'nail_biter' => $this->nailBiter($finishedMatches),
            'penalty_magnet' => $this->penaltyMagnet($finishedMatches),
            'efficiency' => $countThrows ? $this->efficiency($finishedMatches) : null,
            'schluck_olymp' => $this->schluckOlymp($finishedMatches),
            'cup_king' => $tournament->determine_cup_king ? $this->cupKing($tournament) : null,
            'total_cups' => (int) $finishedMatches->flatMap->stats->sum('cups_scored'),
        ];
    }

    /**
     * The special-prize awards derived from the fun-stat summary — every
     * non-null stat that names a team or player, normalised to a printable
     * list (used by the certificate PDF). The champion and the total cup
     * count are intentionally excluded: the champion is already honoured by
     * the placement certificate, and the cup total is a scalar, not a prize.
     *
     * Marathon and Knapper Krimi each name the two teams of a single match;
     * both teams receive the award. The Wurfkönig is the only player-based
     * prize and is rendered with its own certificate wording.
     *
     * @return list<array{key:string, label:string, type:string, subjects:list<string>}>
     */
    public function awards(Tournament $tournament): array
    {
        $summary = $this->summary($tournament);

        $definitions = [
            'sharpest_shooter' => 'Schärfste Schützen',
            'water_spitter' => 'Wasserspeier',
            'blitz_win' => 'Blitzsieg',
            'marathon' => 'Marathonspieler',
            'nail_biter' => 'Knapper Krimi',
            'penalty_magnet' => 'Strafbechermagnet',
            'efficiency' => 'Effizienzrate',
            'schluck_olymp' => 'Schluck-Olymp',
            'cup_king' => 'Wurfkönig',
        ];

        $awards = [];

        foreach ($definitions as $key => $label) {
            $stat = $summary[$key] ?? null;

            if (! $stat) {
                continue;
            }

            if ($key === 'cup_king') {
                $awards[] = ['key' => $key, 'label' => $label, 'type' => 'player', 'subjects' => [$stat['name']]];

                continue;
            }

            $subjects = $stat['teams'] ?? [$stat['team']];
            $awards[] = ['key' => $key, 'label' => $label, 'type' => 'team', 'subjects' => array_values($subjects)];
        }

        return $awards;
    }

    /**
     * Wurfkönig — the individual player who hit the most cups across the
     * tournament, summed from the per-match cup distributions.
     */
    private function cupKing(Tournament $tournament): ?array
    {
        $row = MatchMemberCup::query()
            ->join('team_members', 'team_members.id', '=', 'match_member_cups.team_member_id')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->join('matches', 'matches.id', '=', 'match_member_cups.match_id')
            ->where('matches.tournament_id', $tournament->id)
            ->groupBy('team_members.id', 'team_members.name', 'teams.name')
            ->selectRaw('team_members.name as member_name, teams.name as team_name, SUM(match_member_cups.cups_hit) as total')
            ->orderByDesc('total')
            ->first();

        if (! $row || (int) $row->total === 0) {
            return null;
        }

        return [
            'name' => $row->member_name,
            'team' => $row->team_name,
            'cups' => (int) $row->total,
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

    /**
     * Knapper Krimi — match with the smallest cup difference. Ties broken
     * by total cups scored (higher = more action).
     */
    private function nailBiter(Collection $matches): ?array
    {
        $closest = null;
        foreach ($matches as $match) {
            $home = $match->stats->firstWhere('team_id', $match->home_team_id);
            $away = $match->stats->firstWhere('team_id', $match->away_team_id);
            if (! $home || ! $away) {
                continue;
            }

            $diff = abs($home->cups_scored - $away->cups_scored);
            $total = $home->cups_scored + $away->cups_scored;

            $isCloser = $closest === null
                || $diff < $closest['diff']
                || ($diff === $closest['diff'] && $total > $closest['total']);

            if ($isCloser) {
                $winnerIsHome = $match->winner_team_id === $match->home_team_id;
                $closest = [
                    'teams' => array_filter([
                        $winnerIsHome ? $match->homeTeam?->name : $match->awayTeam?->name,
                        $winnerIsHome ? $match->awayTeam?->name : $match->homeTeam?->name,
                    ]),
                    'score' => $winnerIsHome
                        ? "$home->cups_scored:$away->cups_scored"
                        : "$away->cups_scored:$home->cups_scored",
                    'diff' => $diff,
                    'total' => $total,
                ];
            }
        }

        if ($closest === null) {
            return null;
        }

        unset($closest['total']);

        return $closest;
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
        if (! $row || (int) $row['penalty_cups'] === 0) {
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

    /**
     * Schluck-Olymp — team that "drank" the most cups across the tournament.
     *
     * Per match a team drinks:
     *   • the opponent's cups_scored (= own cups that got drunk during play)
     *   • own penalty cups
     *   • if losing: the winner's remaining cups (= absolute cup difference),
     *     since per Bierpong tradition the loser empties what's still standing
     *     on the winner's side.
     *
     * We derive "cups per side" implicitly from the winner's score (a winner
     * by definition has cleared all the opponent's cups), so this works for
     * any custom cup count without an explicit setting.
     */
    private function schluckOlymp(Collection $matches): ?array
    {
        $drinks = [];

        foreach ($matches as $match) {
            $home = $match->stats->firstWhere('team_id', $match->home_team_id);
            $away = $match->stats->firstWhere('team_id', $match->away_team_id);
            if (! $home || ! $away) {
                continue;
            }

            $diff = abs($home->cups_scored - $away->cups_scored);
            $homeLost = $match->winner_team_id === $away->team_id;
            $awayLost = $match->winner_team_id === $home->team_id;

            // Home drinks: away cups (during play) + own penalty + remaining if lost.
            $drinks[$home->team_id] = [
                'name' => $home->team?->name,
                'cups' => ($drinks[$home->team_id]['cups'] ?? 0)
                    + $away->cups_scored
                    + $home->penalty_cups
                    + ($homeLost ? $diff : 0),
            ];
            // Away drinks: home cups (during play) + own penalty + remaining if lost.
            $drinks[$away->team_id] = [
                'name' => $away->team?->name,
                'cups' => ($drinks[$away->team_id]['cups'] ?? 0)
                    + $home->cups_scored
                    + $away->penalty_cups
                    + ($awayLost ? $diff : 0),
            ];
        }

        $row = collect($drinks)->sortByDesc('cups')->first();
        if (! $row || (int) $row['cups'] === 0) {
            return null;
        }

        return [
            'team' => $row['name'] ?? '—',
            'cups' => (int) $row['cups'],
        ];
    }
}
