<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('rejects an empty new-table name', function () {
    Livewire::test('pages::admin-setup')
        ->set('newTableName', '')
        ->call('addTable')
        ->assertHasErrors(['newTableName' => 'required']);
});

it('allows two teams to share the same name', function () {
    // There is no unique constraint on team names. Two teams "Müller vs Müller"
    // would be a valid (if cheeky) tournament configuration.
    Tournament::factory()->create();

    Livewire::test('pages::admin-setup')
        ->set('newTeamName', 'Team Müller')
        ->set('newTeamColor', '#ff0000')
        ->call('addTeam')
        ->assertHasNoErrors()
        ->set('newTeamName', 'Team Müller')
        ->set('newTeamColor', '#00ff00')
        ->call('addTeam')
        ->assertHasNoErrors();

    expect(Team::where('name', 'Team Müller')->count())->toBe(2);
});

it('refuses to add a table once the tournament is no longer in setup', function () {
    Tournament::factory()->create(['status' => 'group']);

    Livewire::test('pages::admin-setup')
        ->set('newTableName', 'Heimlich gewünschter Tisch')
        ->call('addTable')
        ->assertStatus(422);

    expect(Table::where('name', 'Heimlich gewünschter Tisch')->exists())->toBeFalse();
});

it('refuses to add a team once the tournament is no longer in setup', function () {
    Tournament::factory()->create(['status' => 'ko']);

    Livewire::test('pages::admin-setup')
        ->set('newTeamName', 'Späte Truppe')
        ->call('addTeam')
        ->assertStatus(422);

    expect(Team::where('name', 'Späte Truppe')->exists())->toBeFalse();
});

it('refuses to remove a table during the running group phase', function () {
    $tournament = Tournament::factory()->create(['status' => 'group']);
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);

    Livewire::test('pages::admin-setup')
        ->call('removeTable', $table->id)
        ->assertStatus(422);

    expect(Table::find($table->id))->not->toBeNull();
});

it('refuses to remove a team during the running KO phase', function () {
    $tournament = Tournament::factory()->create(['status' => 'ko']);
    $team = Team::factory()->create(['tournament_id' => $tournament->id]);

    Livewire::test('pages::admin-setup')
        ->call('removeTeam', $team->id)
        ->assertStatus(422);

    expect(Team::find($team->id))->not->toBeNull();
});

it('keeps tables and teams when resetting an in-progress tournament', function () {
    $tournament = Tournament::factory()->create(['status' => 'group']);
    Table::factory()->count(3)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(6)->create(['tournament_id' => $tournament->id]);

    Livewire::test('pages::admin-setup')
        ->call('resetTournament');

    $tournament = $tournament->fresh();
    expect($tournament->status)->toBe('setup')
        ->and($tournament->tables()->count())->toBe(3)
        ->and($tournament->teams()->count())->toBe(6)
        ->and($tournament->matches()->count())->toBe(0)
        ->and($tournament->groups()->count())->toBe(0);
});

it('saves the chosen color when adding a team', function () {
    Tournament::factory()->create();

    Livewire::test('pages::admin-setup')
        ->set('newTeamName', 'Team Rainbow')
        ->set('newTeamColor', '#abcdef')
        ->call('addTeam')
        ->assertHasNoErrors();

    expect(Team::where('name', 'Team Rainbow')->value('color'))->toBe('#abcdef');
});

it('truncates very long color values via the max:9 rule', function () {
    Tournament::factory()->create();

    Livewire::test('pages::admin-setup')
        ->set('newTeamName', 'Team Bad Color')
        ->set('newTeamColor', '#1234567890abcdef')
        ->call('addTeam')
        ->assertHasErrors(['newTeamColor']);
});

it('forbids referees from any setup-page action', function () {
    auth()->logout();
    $this->actingAs(User::factory()->referee()->create());

    $this->get('/setup')->assertForbidden();
});
