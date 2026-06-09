<?php

namespace Database\Factories;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Cup',
            'status' => 'setup',
            'group_match_duration_minutes' => 10,
            'ko_match_duration_minutes' => 15,
            'count_throws' => true,
        ];
    }
}
