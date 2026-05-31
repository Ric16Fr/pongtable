<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->referee()->create());
});

it('renders empty state when no tournament exists', function () {
    Livewire::test('pages::dashboard')
        ->assertSee('Kein Turnier vorhanden');
});

it('shows the tournament name and status when one exists', function () {
    Tournament::factory()->create(['name' => 'Pong World Cup', 'status' => 'group']);

    Livewire::test('pages::dashboard')
        ->assertSee('Pong World Cup')
        ->assertSee('Gruppenphase');
});

it('counts matches correctly', function () {
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $home = Team::factory()->create(['tournament_id' => $tournament->id]);
    $away = Team::factory()->create(['tournament_id' => $tournament->id]);

    foreach (['pending', 'pending', 'finished'] as $status) {
        GameMatch::create([
            'tournament_id' => $tournament->id, 'table_id' => $table->id, 'phase' => 'group',
            'home_team_id' => $home->id, 'away_team_id' => $away->id, 'status' => $status,
        ]);
    }

    $component = Livewire::test('pages::dashboard');

    expect($component->instance()->totals)->toBe([
        'matches' => 3,
        'finished' => 1,
        'pending' => 2,
    ]);
});

it('lists active matches in a Live section', function () {
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Live Home']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Live Away']);

    GameMatch::create([
        'tournament_id' => $tournament->id, 'table_id' => $table->id, 'phase' => 'group',
        'home_team_id' => $home->id, 'away_team_id' => $away->id, 'status' => 'active',
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Live Home')
        ->assertSee('Live Away');
});
