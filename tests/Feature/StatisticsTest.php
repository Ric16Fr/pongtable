<?php

use App\Models\Tournament;
use Livewire\Livewire;

it('renders the tournament header by default', function () {
    $tournament = Tournament::factory()->create(['name' => 'Header Cup']);

    Livewire::test('pages::statistics', ['tournamentId' => $tournament->id])
        ->assertSee('Turnier-Statistik')
        ->assertSee('Header Cup');
});

it('hides the header when used embedded (e.g. in the archive)', function () {
    $tournament = Tournament::factory()->create(['name' => 'Embedded Cup']);

    Livewire::test('pages::statistics', [
        'tournamentId' => $tournament->id,
        'showHeader' => false,
    ])
        ->assertDontSee('Turnier-Statistik')
        ->assertDontSee('Embedded Cup');
});
