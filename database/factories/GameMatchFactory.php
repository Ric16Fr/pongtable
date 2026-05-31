<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameMatch>
 */
class GameMatchFactory extends Factory
{
    public function definition(): array
    {
        $tournament = Tournament::factory()->create();
        $table = Table::factory()->create(['tournament_id' => $tournament->id]);

        return [
            'tournament_id' => $tournament->id,
            'phase' => 'group',
            'group_id' => null,
            'ko_round' => null,
            'ko_position' => null,
            'table_id' => $table->id,
            'home_team_id' => Team::factory()->create(['tournament_id' => $tournament->id])->id,
            'away_team_id' => Team::factory()->create(['tournament_id' => $tournament->id])->id,
            'status' => 'pending',
        ];
    }
}
