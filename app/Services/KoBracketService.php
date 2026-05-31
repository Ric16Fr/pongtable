<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class KoBracketService
{
    /**
     * Returns true once every group match is finished.
     */
    public function isGroupPhaseComplete(Tournament $tournament): bool
    {
        return $tournament->matches()
            ->where('phase', 'group')
            ->where('status', '!=', 'finished')
            ->count() === 0;
    }

    /**
     * Generate first KO round + flip the tournament to "ko" status.
     */
    public function startKoPhase(Tournament $tournament): void
    {
        if (! $tournament->isGroupPhase()) {
            return;
        }

        abort_unless($this->isGroupPhaseComplete($tournament), 422, 'Gruppenphase noch nicht abgeschlossen.');

        DB::transaction(function () use ($tournament) {
            $ranked = collect();

            foreach ($tournament->groups()->get() as $group) {
                $standings = $this->groupStandings($group);

                if ($standings->isNotEmpty()) {
                    $ranked->push(['rank' => 1, 'team' => $standings[0]]);
                }
                if ($standings->count() > 1) {
                    $ranked->push(['rank' => 2, 'team' => $standings[1]]);
                }
            }

            if ($ranked->count() < 2) {
                return;
            }

            $winners = $ranked->where('rank', 1)->values();
            $runners = $ranked->where('rank', 2)->values();

            $participantCount = $winners->count() + $runners->count();
            $roundSize = (int) pow(2, ceil(log(max($participantCount, 2), 2)));
            $koRound = (int) ($roundSize / 2);

            $tables = $tournament->tables()->orderBy('id')->get();
            $matchIndex = 0;

            // Cross-bracket: Winner i vs Runner-up (count - 1 - i)
            foreach ($winners as $i => $winner) {
                $opponent = $runners[$winners->count() - 1 - $i] ?? $runners[$i] ?? null;
                if (! $opponent) {
                    continue;
                }

                GameMatch::create([
                    'tournament_id' => $tournament->id,
                    'phase' => 'ko',
                    'ko_round' => $koRound,
                    'ko_position' => $matchIndex,
                    'table_id' => $tables[$matchIndex % $tables->count()]->id,
                    'home_team_id' => $winner['team']->id,
                    'away_team_id' => $opponent['team']->id,
                    'status' => 'pending',
                ]);
                $matchIndex++;
            }

            $tournament->update(['status' => 'ko']);
        });
    }

    /**
     * Move a finished KO match's winner into the next-round bracket slot.
     * If this was the final, mark the tournament as finished.
     */
    public function advanceKoWinner(GameMatch $match): void
    {
        if ($match->phase !== 'ko' || $match->status !== 'finished' || ! $match->winner_team_id) {
            return;
        }

        $tournament = $match->tournament;

        if ($match->ko_round <= 1) {
            $tournament->update(['status' => 'finished']);

            return;
        }

        $nextRound = (int) ($match->ko_round / 2);
        $nextPosition = (int) floor($match->ko_position / 2);

        $defaults = [
            'tournament_id' => $tournament->id,
            'phase' => 'ko',
            'ko_round' => $nextRound,
            'ko_position' => $nextPosition,
        ];

        $tables = $tournament->tables()->orderBy('id')->get();
        $tableId = $tables[$nextPosition % max(1, $tables->count())]->id;

        $nextMatch = GameMatch::firstOrCreate($defaults, [
            'table_id' => $tableId,
            'status' => 'pending',
        ]);

        if ($match->ko_position % 2 === 0) {
            $nextMatch->update(['home_team_id' => $match->winner_team_id]);
        } else {
            $nextMatch->update(['away_team_id' => $match->winner_team_id]);
        }
    }

    /**
     * Ranked teams for a group, sorted by points → cup diff → cups scored.
     *
     * @return Collection<int, Team>
     */
    public function groupStandings($group): Collection
    {
        return $group->teams()
            ->withPivot('points', 'wins', 'losses', 'cups_scored_total', 'cups_conceded_total')
            ->orderByPivot('points', 'desc')
            ->orderByRaw('(group_team.cups_scored_total - group_team.cups_conceded_total) DESC')
            ->orderByPivot('cups_scored_total', 'desc')
            ->get();
    }
}
