<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

class GroupGeneratorService
{
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
}
