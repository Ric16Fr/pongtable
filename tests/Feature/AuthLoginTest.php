<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the login screen at /login', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Anmelden');
});

it('logs a user in by name + password', function () {
    User::create(['name' => 'admin', 'password' => Hash::make('secret'), 'role' => 'admin']);

    $this->post('/login', ['name' => 'admin', 'password' => 'secret'])
        ->assertRedirect('/dashboard');

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->name)->toBe('admin');
});

it('rejects invalid credentials', function () {
    User::create(['name' => 'admin', 'password' => Hash::make('secret'), 'role' => 'admin']);

    $this->post('/login', ['name' => 'admin', 'password' => 'wrong'])
        ->assertSessionHasErrors();

    expect(auth()->check())->toBeFalse();
});

it('blocks the registration route (registration disabled)', function () {
    $this->get('/register')->assertNotFound();
});
