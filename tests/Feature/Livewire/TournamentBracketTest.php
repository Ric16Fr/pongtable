<?php

use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['name' => 'Bracket Cup']);
    $this->table = Table::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Tisch X']);
});

it('renders empty bracket gracefully', function () {
    Livewire::test('pages::tournament-bracket', ['tournamentId' => $this->tournament->id])
        ->assertOk();
});

it('renders the group standings section when groups exist', function () {
    $group = Group::create([
        'tournament_id' => $this->tournament->id,
        'table_id' => $this->table->id,
        'name' => 'Gruppe A',
    ]);

    $alpha = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Bracket Alpha']);
    $beta = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Bracket Beta']);
    $group->teams()->attach([$alpha->id, $beta->id]);

    Livewire::test('pages::tournament-bracket', ['tournamentId' => $this->tournament->id])
        ->assertSee('Gruppenphase')
        ->assertSee('Gruppe A')
        ->assertSee('Bracket Alpha')
        ->assertSee('Bracket Beta');
});

it('renders the correct round labels in the KO bracket', function () {
    $home = Team::factory()->create(['tournament_id' => $this->tournament->id]);
    $away = Team::factory()->create(['tournament_id' => $this->tournament->id]);

    GameMatch::create([
        'tournament_id' => $this->tournament->id, 'table_id' => $this->table->id,
        'phase' => 'ko', 'ko_round' => 2, 'ko_position' => 0,
        'home_team_id' => $home->id, 'away_team_id' => $away->id, 'status' => 'pending',
    ]);

    GameMatch::create([
        'tournament_id' => $this->tournament->id, 'table_id' => $this->table->id,
        'phase' => 'ko', 'ko_round' => 1, 'ko_position' => 0,
        'home_team_id' => null, 'away_team_id' => null, 'status' => 'pending',
    ]);

    Livewire::test('pages::tournament-bracket', ['tournamentId' => $this->tournament->id])
        ->assertSee('Halbfinale')
        ->assertSee('Finale');
});

it('marks the live matches', function () {
    $home = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Active Home']);
    $away = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Active Away']);

    GameMatch::create([
        'tournament_id' => $this->tournament->id, 'table_id' => $this->table->id,
        'phase' => 'group',
        'home_team_id' => $home->id, 'away_team_id' => $away->id, 'status' => 'active',
    ]);

    Livewire::test('pages::tournament-bracket', ['tournamentId' => $this->tournament->id])
        ->assertSeeInOrder(['Live', 'Active Home', 'Active Away']);
});
