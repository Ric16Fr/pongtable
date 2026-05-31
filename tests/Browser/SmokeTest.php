<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

it('public routes load without JS errors', function () {
    $tournament = Tournament::factory()->create(['name' => 'Smoke Cup']);

    visit(['/', '/login', '/t/'.$tournament->public_token])
        ->assertNoSmoke();
});

it('authenticated routes load without JS errors for admin', function () {
    $this->actingAs(User::factory()->admin()->create());

    $tournament = Tournament::factory()->create(['name' => 'Smoke Cup', 'status' => 'group']);
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);

    visit(['/dashboard', '/matches', '/setup', '/statistics'])
        ->assertNoSmoke();
});
