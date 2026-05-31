<?php

use App\Models\User;

it('redirects guests to login for auth-required routes', function () {
    foreach (['/dashboard', '/matches', '/setup', '/statistics'] as $path) {
        $this->get($path)->assertRedirect('/login');
    }
});

it('allows referees on shared routes but blocks admin-only routes', function () {
    $referee = User::factory()->referee()->create();
    $this->actingAs($referee);

    $this->get('/dashboard')->assertOk();
    $this->get('/matches')->assertOk();

    $this->get('/setup')->assertForbidden();
    $this->get('/statistics')->assertForbidden();
});

it('allows admins on every route', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get('/dashboard')->assertOk();
    $this->get('/matches')->assertOk();
    $this->get('/setup')->assertOk();
    $this->get('/statistics')->assertOk();
});
