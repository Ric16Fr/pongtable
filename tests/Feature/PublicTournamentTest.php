<?php

use App\Models\Tournament;

it('shows a public tournament page by token', function () {
    $tournament = Tournament::factory()->create(['name' => 'Pong Cup XYZ']);

    $this->get('/t/'.$tournament->public_token)
        ->assertOk()
        ->assertSee('Pong Cup XYZ');
});

it('returns 404 for unknown tokens', function () {
    $this->get('/t/00000000-0000-0000-0000-000000000000')->assertNotFound();
});

it('auto-generates a public token on tournament creation', function () {
    $tournament = Tournament::factory()->create();
    expect($tournament->public_token)->not->toBeEmpty();
});
