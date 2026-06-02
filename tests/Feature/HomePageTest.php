<?php

use App\Models\Tournament;

use function Pest\Laravel\get;

it('renders home page when no tournament exists', function (): void {
    get(route('home'))
        ->assertOk()
        ->assertSee('pongtable')
        ->assertSee('Schiri-Login');
});

it('renders home page when a tournament exists', function (): void {
    Tournament::factory()->create(['name' => 'Test Cup']);

    get(route('home'))
        ->assertOk()
        ->assertSee('Test Cup');
});
