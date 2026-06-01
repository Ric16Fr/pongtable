<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tournament = Tournament::firstOrCreate(
            ['name' => 'Bierpong WM '.now()->year],
            [
                'group_match_duration_minutes' => 20,
                'ko_match_duration_minutes' => 20,
                'status' => 'setup',
            ],
        );

        if ($tournament->tables()->doesntExist()) {
            Table::create(['tournament_id' => $tournament->id, 'name' => 'Tisch Rot']);
            Table::create(['tournament_id' => $tournament->id, 'name' => 'Tisch Blau']);
        }

        $teams = [
            ['Team Shotgun', '#f59e0b'],
            ['Team Bierherz', '#ef4444'],
            ['Team NullPointer', '#3b82f6'],
            ['Team 404', '#8b5cf6'],
            ['Team Overflowz', '#10b981'],
            ['Team Syntax Error', '#f97316'],
            ['Team Legacy Code', '#64748b'],
            ['Team Hot Fix', '#ec4899'],
        ];

        if ($tournament->teams()->doesntExist()) {
            foreach ($teams as [$name, $color]) {
                Team::create([
                    'tournament_id' => $tournament->id,
                    'name' => $name,
                    'color' => $color,
                ]);
            }
        }
    }
}
