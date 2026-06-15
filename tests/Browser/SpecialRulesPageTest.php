<?php

use App\Models\Tournament;
use App\Models\User;

it('renders the sonderregeln page with both sections for an admin', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create(['name' => 'Sonder Cup']);

    visit('/sonderregeln')
        ->assertSee('Sonderregeln & Einstellungen')
        ->assertSee('Einstellungen')
        ->assertSee('Name & Match-Dauer')
        ->assertSee('Sonderregeln')
        ->assertSee('Würfe zählen')
        ->assertNoJavascriptErrors();
});

it('shows the special-rules entry in the sidebar for admins', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create();

    visit('/dashboard')
        ->assertSee('Sonderregeln & Einstellungen');
});

it('hides the special-rules entry in the sidebar for referees', function () {
    $this->actingAs(User::factory()->referee()->create());
    Tournament::factory()->create();

    visit('/dashboard')
        ->assertDontSee('Sonderregeln & Einstellungen');
});

it('forbids referees from reaching the sonderregeln page', function () {
    $this->actingAs(User::factory()->referee()->create());

    $this->get('/sonderregeln')->assertForbidden();
});

it('persists name and match durations through the single save button', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tournament = Tournament::factory()->create(['name' => 'Pre Cup']);

    visit('/sonderregeln')
        ->fill('tournamentName', 'Browser Sonder Cup')
        ->fill('groupMinutes', '8')
        ->fill('koMinutes', '12')
        ->press('Speichern')
        ->assertSee('Einstellungen gespeichert.')
        ->assertNoJavascriptErrors();

    $tournament = $tournament->fresh();
    expect($tournament->name)->toBe('Browser Sonder Cup')
        ->and($tournament->group_match_duration_minutes)->toBe(8)
        ->and($tournament->ko_match_duration_minutes)->toBe(12);
});

it('shows the description text that explains what disabling the throws counter does', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create();

    visit('/sonderregeln')
        ->assertSee('Strafbecher')
        ->assertSee('Statistiken')
        ->assertNoJavascriptErrors();
});
