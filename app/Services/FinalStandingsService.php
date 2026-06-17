<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Computes the definitive final placement for every team in a tournament,
 * combining the KO bracket results with the group phase and placement
 * matches.
 *
 * Ordering rules (best first):
 *   1. KO participants, ranked by how far they advanced:
 *        • the champion (winner of the final, ko_round = 1)
 *        • then everyone else by the round they were eliminated in
 *          (a later round = smaller ko_round = better placement),
 *          ties broken by tournament cup difference, then cups scored.
 *   2. Teams that never reached the KO bracket, ordered by their overall
 *      group standings and overridden by any finished placement matches.
 */
class FinalStandingsService
{
    /**
     * The final standings, best team first.
     *
     * @return Collection<int, array{rank:int, team:Team}>
     */
    public function standings(Tournament $tournament): Collection
    {
        $tournament->loadMissing(['teams', 'groups.teams', 'matches']);

        $stats = $this->teamStats($tournament);
        $koMatches = $tournament->matches
            ->where('phase', 'ko')
            ->where('status', 'finished');

        $championId = $koMatches->firstWhere('ko_round', 1)?->winner_team_id;
        $eliminationRound = $this->eliminationRounds($koMatches);
        $koTeamIds = $this->koParticipantIds($koMatches);

        $rankedKo = $tournament->teams
            ->whereIn('id', $koTeamIds)
            ->sort(fn (Team $a, Team $b) => $this->compareKoTeams($a, $b, $championId, $eliminationRound, $stats))
            ->values();

        $nonKo = $tournament->teams->reject(fn (Team $team) => in_array($team->id, $koTeamIds, true));
        $rankedNonKo = $this->orderNonKoTeams($tournament, $nonKo, $stats);

        return $rankedKo
            ->concat($rankedNonKo)
            ->values()
            ->map(fn (Team $team, int $index) => ['rank' => $index + 1, 'team' => $team]);
    }

    /**
     * Per-team group statistics used for tie-breaking.
     *
     * @return array<int, array{points:int, cup_diff:int, cups_scored:int}>
     */
    private function teamStats(Tournament $tournament): array
    {
        $stats = [];

        foreach ($tournament->groups as $group) {
            foreach ($group->teams as $team) {
                $stats[$team->id] = [
                    'points' => (int) $team->pivot->points,
                    'cup_diff' => (int) ($team->pivot->cups_scored_total - $team->pivot->cups_conceded_total),
                    'cups_scored' => (int) $team->pivot->cups_scored_total,
                ];
            }
        }

        return $stats;
    }

    /**
     * Maps each eliminated KO team to the ko_round it lost in. A team loses
     * at most once in a single-elimination bracket, so the champion never
     * appears here.
     *
     * @param  Collection<int, GameMatch>  $koMatches
     * @return array<int, int> team_id => ko_round
     */
    private function eliminationRounds(Collection $koMatches): array
    {
        $rounds = [];

        foreach ($koMatches as $match) {
            if (! $match->winner_team_id) {
                continue;
            }

            $loserId = $match->winner_team_id === $match->home_team_id
                ? $match->away_team_id
                : $match->home_team_id;

            if ($loserId) {
                $rounds[$loserId] = (int) $match->ko_round;
            }
        }

        return $rounds;
    }

    /**
     * All team ids that appeared in the KO bracket.
     *
     * @param  Collection<int, GameMatch>  $koMatches
     * @return array<int, int>
     */
    private function koParticipantIds(Collection $koMatches): array
    {
        return $koMatches
            ->flatMap(fn (GameMatch $match) => [$match->home_team_id, $match->away_team_id])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $eliminationRound
     * @param  array<int, array{points:int, cup_diff:int, cups_scored:int}>  $stats
     */
    private function compareKoTeams(Team $a, Team $b, ?int $championId, array $eliminationRound, array $stats): int
    {
        if ($a->id === $championId) {
            return -1;
        }
        if ($b->id === $championId) {
            return 1;
        }

        $aRound = $eliminationRound[$a->id] ?? PHP_INT_MAX;
        $bRound = $eliminationRound[$b->id] ?? PHP_INT_MAX;
        if ($aRound !== $bRound) {
            return $aRound <=> $bRound;
        }

        return $this->compareByStats($a, $b, $stats);
    }

    /**
     * Orders the teams that never reached the KO bracket by their overall
     * group standings, then lets finished placement matches override that
     * order (the winner of a placement pairing takes the better slot).
     *
     * @param  Collection<int, Team>  $teams
     * @param  array<int, array{points:int, cup_diff:int, cups_scored:int}>  $stats
     * @return Collection<int, Team>
     */
    private function orderNonKoTeams(Tournament $tournament, Collection $teams, array $stats): Collection
    {
        $ordered = $teams
            ->sort(fn (Team $a, Team $b) => $this->compareByStats($a, $b, $stats))
            ->values();

        $placementMatches = $tournament->matches
            ->where('phase', 'placement')
            ->where('status', 'finished')
            ->filter(fn (GameMatch $match) => $match->winner_team_id);

        foreach ($placementMatches as $match) {
            $loserId = $match->winner_team_id === $match->home_team_id
                ? $match->away_team_id
                : $match->home_team_id;

            $winnerIndex = $ordered->search(fn (Team $team) => $team->id === $match->winner_team_id);
            $loserIndex = $ordered->search(fn (Team $team) => $team->id === $loserId);

            if ($winnerIndex !== false && $loserIndex !== false && $loserIndex < $winnerIndex) {
                $winner = $ordered[$winnerIndex];
                $ordered[$winnerIndex] = $ordered[$loserIndex];
                $ordered[$loserIndex] = $winner;
            }
        }

        return $ordered->values();
    }

    /**
     * Points → cup difference → cups scored → stable team id.
     *
     * @param  array<int, array{points:int, cup_diff:int, cups_scored:int}>  $stats
     */
    private function compareByStats(Team $a, Team $b, array $stats): int
    {
        $aStats = $stats[$a->id] ?? ['points' => 0, 'cup_diff' => 0, 'cups_scored' => 0];
        $bStats = $stats[$b->id] ?? ['points' => 0, 'cup_diff' => 0, 'cups_scored' => 0];

        return $bStats['points'] <=> $aStats['points']
            ?: $bStats['cup_diff'] <=> $aStats['cup_diff']
            ?: $bStats['cups_scored'] <=> $aStats['cups_scored']
            ?: $a->id <=> $b->id;
    }
}
