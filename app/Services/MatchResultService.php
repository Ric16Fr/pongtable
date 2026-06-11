<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchStat;
use Illuminate\Support\Facades\DB;

class MatchResultService
{
    public function __construct(private KoBracketService $koBracketService) {}

    /**
     * Move match from "pending" to "pre_entry".
     */
    public function startPreEntry(GameMatch $match): void
    {
        if ($match->status !== 'pending') {
            return;
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            return;
        }

        $match->update(['status' => 'pre_entry']);
    }

    /**
     * Save pre-match data and start the active timer.
     *
     * @param  array{home_throws:int, home_penalty_cups:int, away_throws:int, away_penalty_cups:int}  $data
     */
    public function startMatch(GameMatch $match, array $data): void
    {
        if (! in_array($match->status, ['pending', 'pre_entry'], true)) {
            return;
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            return;
        }

        DB::transaction(function () use ($match, $data) {
            MatchStat::updateOrCreate(
                ['match_id' => $match->id, 'team_id' => $match->home_team_id],
                ['throws' => $data['home_throws'], 'penalty_cups' => $data['home_penalty_cups']],
            );

            MatchStat::updateOrCreate(
                ['match_id' => $match->id, 'team_id' => $match->away_team_id],
                ['throws' => $data['away_throws'], 'penalty_cups' => $data['away_penalty_cups']],
            );

            $match->update([
                'status' => 'active',
                'started_at' => now(),
            ]);
        });
    }

    /**
     * End the timer; move to "scoring".
     */
    public function endTimer(GameMatch $match): void
    {
        if ($match->status !== 'active') {
            return;
        }

        $duration = $match->started_at ? now()->diffInSeconds($match->started_at) : null;
        if ($duration !== null) {
            $duration = (int) abs($duration);
        }

        DB::transaction(function () use ($match, $duration) {
            $match->update([
                'status' => 'scoring',
                'ended_at' => now(),
            ]);

            foreach ($match->stats as $stat) {
                $stat->update(['duration_seconds' => $duration]);
            }
        });
    }

    /**
     * Save cups scored and finalize the result.
     *
     * @param  int|null  $suddenDeathWinnerId  When the KO sudden-death rule is active and the
     *                                         cups are tied, the referee-selected winning team.
     */
    public function saveResult(GameMatch $match, int $homeCups, int $awayCups, ?int $suddenDeathWinnerId = null): void
    {
        if (! in_array($match->status, ['scoring', 'active'], true)) {
            return;
        }

        $match->loadMissing(['stats', 'homeTeam', 'awayTeam', 'tournament']);

        DB::transaction(function () use ($match, $homeCups, $awayCups, $suddenDeathWinnerId) {
            $home = $match->stats->firstWhere('team_id', $match->home_team_id);
            $away = $match->stats->firstWhere('team_id', $match->away_team_id);

            $home?->update(['cups_scored' => $homeCups]);
            $away?->update(['cups_scored' => $awayCups]);

            $winner = $this->determineWinner($match, $homeCups, $awayCups, $home, $away, $suddenDeathWinnerId);

            $match->update([
                'status' => 'finished',
                'winner_team_id' => $winner,
                'ended_at' => $match->ended_at ?? now(),
            ]);

            if ($match->phase === 'group') {
                $this->updateGroupStandings($match, $homeCups, $awayCups, $winner);
            }

            if ($match->phase === 'ko') {
                $this->koBracketService->advanceKoWinner($match->fresh());
            }
        });
    }

    private function determineWinner(GameMatch $match, int $homeCups, int $awayCups, ?MatchStat $home, ?MatchStat $away, ?int $suddenDeathWinnerId = null): int
    {
        if ($homeCups > $awayCups) {
            return $match->home_team_id;
        }

        if ($awayCups > $homeCups) {
            return $match->away_team_id;
        }

        // Cups tied. When the KO sudden-death rule is active, the referee decides
        // the winner manually (a real-life Schere-Stein-Papier shoot-out) instead
        // of the automatic throws/penalty tiebreaker below.
        if ($match->phase === 'ko'
            && ($match->tournament?->ko_sudden_death ?? false)
            && in_array($suddenDeathWinnerId, [$match->home_team_id, $match->away_team_id], true)) {
            return $suddenDeathWinnerId;
        }

        // Tiebreaker 1: fewer throws wins
        if ($home && $away) {
            if ($home->throws < $away->throws) {
                return $match->home_team_id;
            }
            if ($away->throws < $home->throws) {
                return $match->away_team_id;
            }

            // Tiebreaker 2: fewer penalty cups wins
            if ($home->penalty_cups < $away->penalty_cups) {
                return $match->home_team_id;
            }
            if ($away->penalty_cups < $home->penalty_cups) {
                return $match->away_team_id;
            }
        }

        // Fallback: home wins
        return $match->home_team_id;
    }

    private function updateGroupStandings(GameMatch $match, int $homeCups, int $awayCups, int $winnerId): void
    {
        if (! $match->group_id) {
            return;
        }

        $group = $match->group;
        if (! $group) {
            return;
        }

        $homeRow = $group->teams()->where('teams.id', $match->home_team_id)->first();
        $awayRow = $group->teams()->where('teams.id', $match->away_team_id)->first();

        if ($homeRow) {
            $pivot = $homeRow->pivot;
            $group->teams()->updateExistingPivot($match->home_team_id, [
                'points' => $pivot->points + ($winnerId === $match->home_team_id ? 3 : 0),
                'wins' => $pivot->wins + ($winnerId === $match->home_team_id ? 1 : 0),
                'losses' => $pivot->losses + ($winnerId === $match->home_team_id ? 0 : 1),
                'cups_scored_total' => $pivot->cups_scored_total + $homeCups,
                'cups_conceded_total' => $pivot->cups_conceded_total + $awayCups,
            ]);
        }

        if ($awayRow) {
            $pivot = $awayRow->pivot;
            $group->teams()->updateExistingPivot($match->away_team_id, [
                'points' => $pivot->points + ($winnerId === $match->away_team_id ? 3 : 0),
                'wins' => $pivot->wins + ($winnerId === $match->away_team_id ? 1 : 0),
                'losses' => $pivot->losses + ($winnerId === $match->away_team_id ? 0 : 1),
                'cups_scored_total' => $pivot->cups_scored_total + $awayCups,
                'cups_conceded_total' => $pivot->cups_conceded_total + $homeCups,
            ]);
        }
    }
}
