<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Table;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        $tournament = Tournament::factory()->create();

        return [
            'tournament_id' => $tournament->id,
            'table_id' => Table::factory()->create(['tournament_id' => $tournament->id])->id,
            'name' => 'Gruppe '.fake()->randomLetter(),
        ];
    }
}
