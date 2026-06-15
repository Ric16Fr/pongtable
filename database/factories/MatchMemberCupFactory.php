<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchMemberCup;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchMemberCup>
 */
class MatchMemberCupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'team_member_id' => TeamMember::factory(),
            'cups_hit' => fake()->numberBetween(0, 10),
        ];
    }
}
