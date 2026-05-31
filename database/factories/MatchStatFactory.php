<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchStat;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchStat>
 */
class MatchStatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'team_id' => Team::factory(),
            'cups_scored' => 0,
            'throws' => 0,
            'penalty_cups' => 0,
            'duration_seconds' => null,
        ];
    }
}
