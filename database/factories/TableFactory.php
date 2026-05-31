<?php

namespace Database\Factories;

use App\Models\Table;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'name' => 'Tisch '.fake()->numberBetween(1, 99),
        ];
    }
}
