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

it('keeps the UI settings section collapsed by default and reveals it on click', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create();

    visit('/sonderregeln')
        ->assertSee('UI-Einstellungen')
        ->assertDontSee('Diese Sektion enthält selten genutzte UI Einstellungen')
        ->click('UI-Einstellungen')
        ->assertSee('Diese Sektion enthält selten genutzte UI Einstellungen')
        ->assertNoJavascriptErrors();
});

it('persists the hide-certificate-circles toggle from the UI settings section', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tournament = Tournament::factory()->create();

    visit('/sonderregeln')
        ->click('UI-Einstellungen')
        ->assertSee('Farbige Kreise in den Urkunden ausblenden')
        ->click('Farbige Kreise in den Urkunden ausblenden')
        ->press('@save-special-rules-button')
        ->assertSee('Einstellungen gespeichert.')
        ->assertNoJavascriptErrors();

    expect($tournament->fresh()->hide_certificate_circles)->toBeTrue();
});

it('saves an entered schedule and publishes the public Turnierplan tab', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tournament = Tournament::factory()->create(['show_schedule' => true]);

    visit('/sonderregeln')
        ->click('UI-Einstellungen')
        ->assertSee('Turnierplan anzeigen')
        ->assertVisible('@schedule-input')
        ->fill('schedule', "Team Eins;Team Zwei\nTeam Drei;Team Vier")
        ->press('@save-special-rules-button')
        ->assertSee('Einstellungen gespeichert.')
        ->assertNoJavascriptErrors();

    expect($tournament->fresh()->schedule)->toBe("Team Eins;Team Zwei\nTeam Drei;Team Vier");

    // The public schedule page now renders the entered matchups.
    visit('/turnierplan')
        ->assertSee('Turnierplan')
        ->assertSee('Team Eins')
        ->assertSee('Team Vier')
        ->assertNoJavascriptErrors();
});

it('shows the description text that explains what disabling the throws counter does', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create();

    visit('/sonderregeln')
        ->assertSee('Strafbecher')
        ->assertSee('Statistiken')
        ->assertNoJavascriptErrors();
});
