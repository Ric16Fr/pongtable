<?php

use App\Models\Tournament;

use function Pest\Laravel\get;

it('404s when no tournament exists', function (): void {
    get(route('schedule'))->assertNotFound();
});

it('404s when the schedule option is off', function (): void {
    Tournament::factory()->create(['show_schedule' => false, 'schedule' => 'A;B']);

    get(route('schedule'))->assertNotFound();
});

it('404s when the option is on but the schedule is empty', function (): void {
    Tournament::factory()->create(['show_schedule' => true, 'schedule' => "   \n ; "]);

    get(route('schedule'))->assertNotFound();
});

it('renders one row per schedule line when enabled', function (): void {
    Tournament::factory()->create([
        'name' => 'Plan Cup',
        'show_schedule' => true,
        'schedule' => "Team Alpha;Team Beta\nTeam Gamma;Team Delta",
    ]);

    get(route('schedule'))
        ->assertOk()
        ->assertSee('Turnierplan')
        ->assertSee('Plan Cup')
        ->assertSee('Team Alpha')
        ->assertSee('Team Beta')
        ->assertSee('Team Gamma')
        ->assertSee('Team Delta');
});

it('shows the Turnierplan tab on the home page only when a public schedule exists', function (): void {
    $tournament = Tournament::factory()->create(['show_schedule' => false]);

    get(route('home'))->assertOk()->assertDontSee(route('schedule'));

    $tournament->update(['show_schedule' => true, 'schedule' => 'A;B']);

    get(route('home'))->assertOk()->assertSee(route('schedule'));
});

it('shows the Turnierplan tab on the rules page when a public schedule exists', function (): void {
    Tournament::factory()->create(['show_schedule' => true, 'schedule' => 'A;B']);

    get(route('rules'))->assertOk()->assertSee(route('schedule'));
});
