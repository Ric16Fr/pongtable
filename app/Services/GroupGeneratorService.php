<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

class GroupGeneratorService
{
    /** Fallback palette for imported teams (no color in CSV). */
    private const COLOR_PALETTE = [
        '#f59e0b', '#3b82f6', '#10b981', '#ef4444',
        '#a855f7', '#f97316', '#06b6d4', '#ec4899',
    ];

    /**
     * Preview the planned distribution of teams across tables without persisting.
     *
     * @return array<int, array{table: string, teams: array<int, string>}>
     */
    public function preview(Tournament $tournament): array
    {
        $tables = $tournament->tables()->orderBy('id')->get();
        $teams = $tournament->teams()->orderBy('id')->get();

        if ($tables->isEmpty() || $teams->isEmpty()) {
            return [];
        }

        $buckets = $tables->mapWithKeys(fn ($table) => [$table->id => [
            'table' => $table->name,
            'teams' => [],
        ]])->all();

        foreach ($teams->values() as $index => $team) {
            $tableId = $tables[$index % $tables->count()]->id;
            $buckets[$tableId]['teams'][] = $team->name;
        }

        return array_values($buckets);
    }

    /**
     * Generate groups + group matches and flip the tournament to "group" phase.
     */
    public function generate(Tournament $tournament): void
    {
        if (! $tournament->isSetup()) {
            return;
        }

        $tables = $tournament->tables()->orderBy('id')->get();
        $teams = $tournament->teams()->inRandomOrder()->get();

        abort_if($tables->isEmpty(), 422, 'Mindestens 1 Tisch erforderlich.');
        abort_if($teams->count() < 2, 422, 'Mindestens 2 Teams erforderlich.');

        DB::transaction(function () use ($tournament, $tables, $teams) {
            $tournament->matches()->delete();
            foreach ($tournament->groups as $g) {
                $g->teams()->detach();
            }
            $tournament->groups()->delete();

            $groupsByTable = [];
            foreach ($tables as $tableIndex => $table) {
                $groupsByTable[$table->id] = Group::create([
                    'tournament_id' => $tournament->id,
                    'table_id' => $table->id,
                    'name' => 'Gruppe '.chr(65 + $tableIndex),
                ]);
            }

            foreach ($teams->values() as $index => $team) {
                $table = $tables[$index % $tables->count()];
                $groupsByTable[$table->id]->teams()->attach($team->id);
            }

            foreach ($tournament->groups()->with('teams')->get() as $group) {
                $groupTeams = $group->teams->values();
                for ($i = 0; $i < $groupTeams->count(); $i++) {
                    for ($j = $i + 1; $j < $groupTeams->count(); $j++) {
                        GameMatch::create([
                            'tournament_id' => $tournament->id,
                            'phase' => 'group',
                            'group_id' => $group->id,
                            'table_id' => $group->table_id,
                            'home_team_id' => $groupTeams[$i]->id,
                            'away_team_id' => $groupTeams[$j]->id,
                            'status' => 'pending',
                        ]);
                    }
                }
            }

            $tournament->update(['status' => 'group']);
        });
    }

    /**
     * Import a pre-drawn group layout from a semicolon-separated CSV.
     *
     * Accepts either multi-line CSV (header row + N team rows) or a flat
     * single-line variant where the leading "Gruppe X" cells mark how many
     * groups there are. Existing teams + groups + matches get replaced.
     */
    public function importFromCsv(Tournament $tournament, string $csv): void
    {
        abort_unless($tournament->isSetup(), 422, 'Turnier ist nicht mehr im Setup.');

        [$groupCount, $teamCells] = app(GroupCsvParser::class)->parse($csv);

        abort_if($groupCount < 1, 422, 'Keine Gruppen in der CSV erkannt.');
        abort_if(count($teamCells) < $groupCount * 2, 422, 'Zu wenige Teams in der CSV — mindestens 2 pro Gruppe.');

        $tables = $tournament->tables()->orderBy('id')->get();
        abort_unless(
            $tables->count() === $groupCount,
            422,
            sprintf('Anzahl Tische (%d) passt nicht zur Anzahl Gruppen in der CSV (%d).', $tables->count(), $groupCount),
        );

        // Modulo distribution mirrors how cells would land if read row-by-row:
        // cell 0 → group 0, cell 1 → group 1, …, cell N → group 0 again.
        $buckets = array_fill(0, $groupCount, []);
        foreach ($teamCells as $index => $teamName) {
            $buckets[$index % $groupCount][] = $teamName;
        }

        DB::transaction(function () use ($tournament, $tables, $buckets, $groupCount) {
            $tournament->matches()->delete();
            foreach ($tournament->groups as $g) {
                $g->teams()->detach();
            }
            $tournament->groups()->delete();
            $tournament->teams()->delete();

            $colorIndex = 0;

            for ($i = 0; $i < $groupCount; $i++) {
                $group = Group::create([
                    'tournament_id' => $tournament->id,
                    'table_id' => $tables[$i]->id,
                    'name' => 'Gruppe '.chr(65 + $i),
                ]);

                $teamIds = [];
                foreach ($buckets[$i] as $teamName) {
                    $team = Team::create([
                        'tournament_id' => $tournament->id,
                        'name' => $teamName,
                        'color' => self::COLOR_PALETTE[$colorIndex % count(self::COLOR_PALETTE)],
                    ]);
                    $colorIndex++;
                    $teamIds[] = $team->id;
                }

                $group->teams()->attach($teamIds);

                // Round-robin matches within the group.
                $teams = $group->teams()->orderBy('teams.id')->get()->values();
                for ($k = 0; $k < $teams->count(); $k++) {
                    for ($j = $k + 1; $j < $teams->count(); $j++) {
                        GameMatch::create([
                            'tournament_id' => $tournament->id,
                            'phase' => 'group',
                            'group_id' => $group->id,
                            'table_id' => $group->table_id,
                            'home_team_id' => $teams[$k]->id,
                            'away_team_id' => $teams[$j]->id,
                            'status' => 'pending',
                        ]);
                    }
                }
            }

            $tournament->update(['status' => 'group']);
        });
    }
}
