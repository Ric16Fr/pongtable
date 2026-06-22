<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

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
     * Returns true once every placement match is finished.
     */
    public function isPlacementRoundComplete(Tournament $tournament): bool
    {
        return $tournament->matches()
            ->where('phase', 'placement')
            ->where('status', '!=', 'finished')
            ->count() === 0;
    }

    /**
     * Advance the tournament out of the group phase. Depending on the
     * "Platzierungsspiele austragen" setting this either starts the
     * placement round for the non-qualified teams first, or generates
     * the KO bracket directly. From the placement phase it generates
     * the KO bracket once all placement matches are finished.
     */
    public function startKoPhase(Tournament $tournament): void
    {
        if ($tournament->isPlacementPhase()) {
            abort_unless($this->isPlacementRoundComplete($tournament), 422, 'Platzierungsspiele noch nicht abgeschlossen.');

            $this->generateKoBracket($tournament);

            return;
        }

        if (! $tournament->isGroupPhase()) {
            return;
        }

        abort_unless($this->isGroupPhaseComplete($tournament), 422, 'Gruppenphase noch nicht abgeschlossen.');

        if ($tournament->play_placement_matches && $this->nonQualifiedTeams($tournament)->count() >= 2) {
            $this->startPlacementRound($tournament);

            return;
        }

        $this->generateKoBracket($tournament);
    }

    /**
     * Generate first KO round + flip the tournament to "ko" status.
     *
     * @throws Throwable
     */
    private function generateKoBracket(Tournament $tournament): void
    {
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
            $roundSize = (int) max($participantCount, 2)
                    |> (fn ($x) => log($x, 2))
                    |> ceil(...)
                    |> (fn ($x) => pow(2, $x));
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
     * Create the placement round for all teams that did not qualify for
     * the KO phase + flip the tournament to "placement" status.
     *
     * Teams are paired bottom-up in the overall standings: the last two
     * play for the last two places, the next two above them for the next
     * two places, and so on. With an odd team count the best non-qualified
     * team has no opponent and simply keeps its place.
     *
     * @throws Throwable
     */
    private function startPlacementRound(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament) {
            $teams = $this->nonQualifiedTeams($tournament);
            $tables = $tournament->tables()->orderBy('id')->get();
            $matchIndex = 0;

            // Pair from the bottom of the table upwards; better-ranked team is home.
            // With an odd team count the loop never reaches index 0, leaving the
            // best non-qualified team without a match.
            for ($i = $teams->count() - 2; $i >= 0; $i -= 2) {
                GameMatch::create([
                    'tournament_id' => $tournament->id,
                    'phase' => 'placement',
                    'ko_position' => $matchIndex,
                    'table_id' => $tables[$matchIndex % $tables->count()]->id,
                    'home_team_id' => $teams[$i]->id,
                    'away_team_id' => $teams[$i + 1]->id,
                    'status' => 'pending',
                ]);
                $matchIndex++;
            }

            $tournament->update(['status' => 'placement']);
        });
    }

    /**
     * All teams that did NOT qualify for the KO phase (everyone below the
     * top two of their group), ordered by overall standings (best first).
     *
     * @return Collection<int, Team>
     */
    public function nonQualifiedTeams(Tournament $tournament): Collection
    {
        $remaining = collect();

        foreach ($tournament->groups()->get() as $group) {
            /** @noinspection PhpParamsInspection */
            $remaining = $remaining->concat($this->groupStandings($group)->slice(2)->values());
        }

        return $remaining
            ->sort(function ($a, $b) {
                if ($a->pivot->points !== $b->pivot->points) {
                    return $b->pivot->points <=> $a->pivot->points;
                }

                $aDiff = $a->pivot->cups_scored_total - $a->pivot->cups_conceded_total;
                $bDiff = $b->pivot->cups_scored_total - $b->pivot->cups_conceded_total;
                if ($aDiff !== $bDiff) {
                    return $bDiff <=> $aDiff;
                }

                if ($a->pivot->cups_scored_total !== $b->pivot->cups_scored_total) {
                    return $b->pivot->cups_scored_total <=> $a->pivot->cups_scored_total;
                }

                return $a->id <=> $b->id;
            })
            ->values();
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
     * Ranked teams for a group, following the official tournament rules:
     * Punkte → direkter Vergleich → Torverhältnis → getroffene Becher.
     *
     * "Direkter Vergleich" is a head-to-head mini-table among teams that
     * are tied on overall points — for a two-way tie it's effectively who
     * won the match between them; for 3+ tied teams it's the points each
     * collected against only the other tied teams.
     *
     * If everything is still equal the rules call for an "Entscheidungsspiel"
     * (decided manually); we fall back to a stable team-id order so the
     * algorithm is deterministic in the meantime.
     *
     * @return Collection<int, Team>
     */
    public function groupStandings($group): Collection
    {
        $teams = $group->teams()
            ->withPivot('points', 'wins', 'losses', 'cups_scored_total', 'cups_conceded_total')
            ->get();

        if ($teams->count() < 2) {
            return $teams;
        }

        $matches = GameMatch::query()
            ->where('group_id', $group->id)
            ->where('status', 'finished')
            ->whereNotNull('winner_team_id')
            ->get(['id', 'home_team_id', 'away_team_id', 'winner_team_id']);

        return $teams
            ->groupBy(fn ($team) => $team->pivot->points)
            ->sortKeysDesc()
            ->flatMap(function ($bucket) use ($matches) {
                if ($bucket->count() === 1) {
                    return $bucket;
                }

                $h2h = $this->headToHeadPoints($matches, $bucket->pluck('id')->all());

                return $bucket->sort(function ($a, $b) use ($h2h) {
                    // 2. Direkter Vergleich — head-to-head points within tied set.
                    if (($h2h[$a->id] ?? 0) !== ($h2h[$b->id] ?? 0)) {
                        return ($h2h[$b->id] ?? 0) <=> ($h2h[$a->id] ?? 0);
                    }

                    // 3. Torverhältnis — overall cup difference.
                    $aDiff = $a->pivot->cups_scored_total - $a->pivot->cups_conceded_total;
                    $bDiff = $b->pivot->cups_scored_total - $b->pivot->cups_conceded_total;
                    if ($aDiff !== $bDiff) {
                        return $bDiff <=> $aDiff;
                    }

                    // 4. Getroffene Becher — overall cups scored.
                    if ($a->pivot->cups_scored_total !== $b->pivot->cups_scored_total) {
                        return $b->pivot->cups_scored_total <=> $a->pivot->cups_scored_total;
                    }

                    // Stable fallback — rules require Entscheidungsspiel here.
                    return $a->id <=> $b->id;
                });
            })
            ->values();
    }

    /**
     * Head-to-head points among a set of tied teams: 3 per win in any match
     * where BOTH participants are part of the tied bucket. Returns a
     * team_id → points map (0 for teams without h2h matches yet).
     *
     * @param  Collection<int, GameMatch>  $matches
     * @param  array<int, int>  $tiedIds
     * @return array<int, int>
     */
    private function headToHeadPoints(Collection $matches, array $tiedIds): array
    {
        $points = array_fill_keys($tiedIds, 0);

        foreach ($matches as $match) {
            if (! in_array($match->home_team_id, $tiedIds, true)
                || ! in_array($match->away_team_id, $tiedIds, true)) {
                continue;
            }

            if ($match->winner_team_id === $match->home_team_id) {
                $points[$match->home_team_id] += 3;
            } elseif ($match->winner_team_id === $match->away_team_id) {
                $points[$match->away_team_id] += 3;
            }
        }

        return $points;
    }
}
