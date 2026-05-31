<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->userName(),
            'email' => null,
            'email_verified_at' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'referee',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function referee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'referee',
        ]);
    }
}
