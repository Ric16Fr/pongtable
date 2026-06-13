<?php

use App\Models\User;

/**
 * Distinctive marker of the inlined app logo SVG (resources/views/components/app-logo-icon.blade.php).
 * The favicon link only references the file, so this attribute is unique to the avatar.
 */
const APP_LOGO_PATH_FRAGMENT = 'aria-label="pongtable"';

it('shows the app logo instead of initials in the admin avatar', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Turnierleitung']));

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee(APP_LOGO_PATH_FRAGMENT, false);
});

it('keeps generated initials for referees instead of the logo', function () {
    $this->actingAs(User::factory()->referee()->create(['name' => 'Schiri Sieben']));

    $this->get('/dashboard')
        ->assertOk()
        ->assertDontSee(APP_LOGO_PATH_FRAGMENT, false);
});
