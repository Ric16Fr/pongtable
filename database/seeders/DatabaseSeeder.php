<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['name' => 'admin'],
            ['password' => Hash::make('password'), 'role' => 'admin'],
        );

        foreach (['ref1', 'ref2'] as $name) {
            User::firstOrCreate(
                ['name' => $name],
                ['password' => Hash::make('password'), 'role' => 'referee'],
            );
        }

        $tournament = Tournament::firstOrCreate(
            ['name' => 'Hackerspace Cup '.now()->year],
            [
                'group_match_duration_minutes' => 10,
                'ko_match_duration_minutes' => 15,
                'status' => 'setup',
            ],
        );

        if ($tournament->tables()->doesntExist()) {
            Table::create(['tournament_id' => $tournament->id, 'name' => 'Tisch 1']);
            Table::create(['tournament_id' => $tournament->id, 'name' => 'Tisch 2']);
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
