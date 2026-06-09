<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

it('renders the setup page for an admin without JS errors', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create(['name' => 'Setup Cup']);

    visit('/setup')
        ->assertSee('Turnier-Setup')
        ->assertSee('Tische')
        ->assertSee('Teams')
        ->assertNoJavascriptErrors();
});

it('lets an admin rename the tournament via the special-rules settings form', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create(['name' => 'Initial Name']);

    visit('/sonderregeln')
        ->fill('tournamentName', 'Browser Test Cup')
        ->press('Speichern')
        ->assertSee('Einstellungen gespeichert.')
        ->assertNoJavascriptErrors();

    expect(Tournament::latest()->first()->name)->toBe('Browser Test Cup');
});

it('hides add-buttons once the tournament has left the setup phase', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tournament = Tournament::factory()->create(['status' => 'group']);
    Table::factory()->create(['tournament_id' => $tournament->id]);

    visit('/setup')
        ->assertDontSee('Tisch hinzufügen')
        ->assertDontSee('Team hinzufügen');
});

it('shows the reset button only after leaving setup phase', function () {
    $this->actingAs(User::factory()->admin()->create());

    Tournament::factory()->create(['status' => 'setup']);
    visit('/setup')->assertDontSee('Turnier zurücksetzen');

    Tournament::query()->delete();
    Tournament::factory()->create(['status' => 'group']);
    visit('/setup')->assertSee('Turnier zurücksetzen');
});

it('forbids referees from reaching the setup page', function () {
    $this->actingAs(User::factory()->referee()->create());

    $this->get('/setup')->assertForbidden();
});

it('shows tables and teams in the setup overview', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tournament = Tournament::factory()->create();
    Table::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Kellerbar Tisch']);
    Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Shotgun']);

    visit('/setup')
        ->assertSee('Kellerbar Tisch')
        ->assertSee('Team Shotgun')
        ->assertNoJavascriptErrors();
});
