<?php

use App\Models\User;

it('redirects guests to login for auth-required routes', function () {
    foreach (['/dashboard', '/matches', '/setup', '/statistics', '/settings', '/sonderregeln'] as $path) {
        $this->get($path)->assertRedirect('/login');
    }
});

it('allows referees on shared routes but blocks admin-only routes', function () {
    $referee = User::factory()->referee()->create();
    $this->actingAs($referee);

    $this->get('/dashboard')->assertOk();
    $this->get('/matches')->assertOk();
    $this->get('/settings')->assertOk();

    $this->get('/setup')->assertForbidden();
    $this->get('/statistics')->assertForbidden();
    $this->get('/sonderregeln')->assertForbidden();
});

it('allows admins on every route', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get('/dashboard')->assertOk();
    $this->get('/matches')->assertOk();
    $this->get('/setup')->assertOk();
    $this->get('/statistics')->assertOk();
    $this->get('/settings')->assertOk();
    $this->get('/sonderregeln')->assertOk();
});

it('shows the special-rules link in the sidebar only for admins', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/dashboard')
        ->assertSee('Sonderregeln &amp; Einstellungen', escape: false)
        ->assertSee('🍺', escape: false);

    $referee = User::factory()->referee()->create();
    $this->actingAs($referee)->get('/dashboard')
        ->assertDontSee('Sonderregeln &amp; Einstellungen', escape: false);
});
