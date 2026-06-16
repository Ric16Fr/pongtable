<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->referee()->create());

    $this->tournament = Tournament::factory()->create();
    $this->table = Table::factory()->create(['tournament_id' => $this->tournament->id]);
    $this->home = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Team Alpha']);
    $this->away = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Team Beta']);
    $this->third = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Team Gamma']);
});

function makeMatch(Tournament $t, Table $table, Team $home, Team $away, string $status): GameMatch
{
    return GameMatch::create([
        'tournament_id' => $t->id,
        'table_id' => $table->id,
        'phase' => 'group',
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => $status,
    ]);
}

it('shows all matches when filter = all', function () {
    makeMatch($this->tournament, $this->table, $this->home, $this->away, 'pending');
    makeMatch($this->tournament, $this->table, $this->home, $this->third, 'active');

    Livewire::test('pages::match-list')
        ->assertSet('filter', 'all')
        ->assertSee('Team Alpha')
        ->assertSee('Team Beta')
        ->assertSee('Team Gamma');
});

it('hides finished matches when filter = live', function () {
    makeMatch($this->tournament, $this->table, $this->home, $this->away, 'pending');
    makeMatch($this->tournament, $this->table, $this->home, $this->third, 'active');

    Livewire::test('pages::match-list')
        ->set('filter', 'live')
        ->assertSee('Team Gamma')
        ->assertDontSee('Team Beta');
});

it('shows only finished matches when filter = finished', function () {
    makeMatch($this->tournament, $this->table, $this->home, $this->away, 'pending');
    makeMatch($this->tournament, $this->table, $this->home, $this->third, 'finished');

    Livewire::test('pages::match-list')
        ->set('filter', 'finished')
        ->assertSee('Team Gamma')
        ->assertDontSee('Team Beta');
});

it('shows the empty state when there are no matches', function () {
    Livewire::test('pages::match-list')
        ->assertSee('Keine Matches in dieser Ansicht');
});

it('filters matches by table', function () {
    $otherTable = Table::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Tisch 2']);

    makeMatch($this->tournament, $this->table, $this->home, $this->away, 'pending');
    makeMatch($this->tournament, $otherTable, $this->home, $this->third, 'pending');

    Livewire::test('pages::match-list')
        ->assertSet('tableFilter', 'all')
        ->set('tableFilter', $this->table->id)
        ->assertSee('Team Beta')
        ->assertDontSee('Team Gamma');
});

it('combines the table filter with the status filter', function () {
    $otherTable = Table::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Tisch 2']);

    makeMatch($this->tournament, $this->table, $this->home, $this->away, 'active');
    makeMatch($this->tournament, $this->table, $this->home, $this->third, 'finished');
    makeMatch($this->tournament, $otherTable, $this->away, $this->third, 'active');

    Livewire::test('pages::match-list')
        ->set('tableFilter', $this->table->id)
        ->set('filter', 'live')
        ->assertSee('Team Beta')
        ->assertDontSee('Team Gamma');
});

it('renders a button for each table of the tournament', function () {
    Table::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Tisch 2']);

    Livewire::test('pages::match-list')
        ->assertSee('Alle Tische')
        ->assertSee($this->table->name)
        ->assertSee('Tisch 2');
});
