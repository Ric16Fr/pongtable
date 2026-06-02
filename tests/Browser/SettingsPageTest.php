<?php

use App\Models\User;

it('renders the settings page for an admin including the schiri sections', function () {
    $this->actingAs(User::factory()->admin()->create());

    visit('/settings')
        ->assertSee('Einstellungen')
        ->assertSee('Passwort ändern')
        ->assertSee('Schiri anlegen')
        ->assertSee('Schiedsrichter')
        ->assertNoJavascriptErrors();
});

it('hides the schiri sections for referees on the settings page', function () {
    $this->actingAs(User::factory()->referee()->create());

    visit('/settings')
        ->assertSee('Passwort ändern')
        ->assertDontSee('Schiri anlegen')
        ->assertNoJavascriptErrors();
});

it('lists existing referees with their names', function () {
    $this->actingAs(User::factory()->admin()->create());
    User::factory()->referee()->create(['name' => 'schiri-alpha']);
    User::factory()->referee()->create(['name' => 'schiri-bravo']);

    visit('/settings')
        ->assertSee('schiri-alpha')
        ->assertSee('schiri-bravo')
        ->assertSee('2')
        ->assertNoJavascriptErrors();
});

it('redirects guests away from settings', function () {
    $this->get('/settings')->assertRedirect('/login');
});

it('keeps the schiri count headline in sync with the actual count', function () {
    $this->actingAs(User::factory()->admin()->create());
    User::factory()->referee()->count(3)->create();

    visit('/settings')
        ->assertSee('Schiedsrichter');
});
