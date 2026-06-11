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

it('shows the custom tournament description on the home page', function (): void {
    Tournament::factory()->create(['description' => 'Unsere eigene Beschreibung.']);

    get(route('home'))
        ->assertOk()
        ->assertSee('Unsere eigene Beschreibung.');
});

it('falls back to the default description when none is set', function (): void {
    Tournament::factory()->create(['description' => null]);

    get(route('home'))
        ->assertOk()
        ->assertSee(Tournament::DEFAULT_DESCRIPTION);
});
