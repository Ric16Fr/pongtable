<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('auto-creates a tournament on first mount when none exists', function () {
    Livewire::test('pages::admin-setup')
        ->assertOk();

    expect(Tournament::count())->toBe(1);
});

it('starts a fresh tournament and switches the active one to it', function () {
    $old = Tournament::factory()->create(['name' => 'Alter Cup', 'status' => 'finished', 'created_at' => now()->subDay()]);

    Livewire::test('pages::admin-setup')
        ->call('createNewTournament')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'create-new-tournament');

    expect(Tournament::count())->toBe(2);

    $latest = Tournament::query()->latest()->first();
    expect($latest->id)->not->toBe($old->id)
        ->and($latest->status)->toBe('setup')
        ->and($old->fresh()->status)->toBe('finished');
    // The old tournament is left completely untouched.
});

it('adds a new table during setup phase', function () {
    Livewire::test('pages::admin-setup')
        ->set('newTableName', 'Tisch Kellerbar')
        ->call('addTable')
        ->assertHasNoErrors()
        ->assertSet('newTableName', '');

    expect(Table::where('name', 'Tisch Kellerbar')->exists())->toBeTrue();
});

it('toggles the inline add-table form open and shut', function () {
    Livewire::test('pages::admin-setup')
        ->assertSet('addingTable', false)
        ->call('startAddingTable')
        ->assertSet('addingTable', true)
        ->set('newTableName', 'wird verworfen')
        ->call('cancelAddingTable')
        ->assertSet('addingTable', false)
        ->assertSet('newTableName', '');
});

it('toggles the inline add-team form open and shut', function () {
    Livewire::test('pages::admin-setup')
        ->assertSet('addingTeam', false)
        ->call('startAddingTeam')
        ->assertSet('addingTeam', true)
        ->set('newTeamName', 'wird verworfen')
        ->call('cancelAddingTeam')
        ->assertSet('addingTeam', false)
        ->assertSet('newTeamName', '');
});

it('removes a table during setup phase', function () {
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);

    Livewire::test('pages::admin-setup')
        ->call('removeTable', $table->id);

    expect(Table::find($table->id))->toBeNull();
});

it('adds a new team', function () {
    Livewire::test('pages::admin-setup')
        ->set('newTeamName', 'Team Shotgun')
        ->call('addTeam')
        ->assertHasNoErrors();

    expect(Team::where('name', 'Team Shotgun')->first())->not->toBeNull();
});

it('rejects empty team or table names', function () {
    Livewire::test('pages::admin-setup')
        ->set('newTeamName', '')
        ->call('addTeam')
        ->assertHasErrors(['newTeamName' => 'required']);
});

it('shows the group preview after clicking showPreview', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);

    Livewire::test('pages::admin-setup')
        ->assertSet('showGroupPreview', false)
        ->call('showPreview')
        ->assertSet('showGroupPreview', true);
});

it('generates groups via confirmGenerate and redirects', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);

    Livewire::test('pages::admin-setup')
        ->call('confirmGenerate')
        ->assertRedirect(route('matches.index'));

    // 4 teams / 2 tables → 2 teams per group → 1 match per group → 2 matches total
    expect($tournament->fresh()->status)->toBe('group')
        ->and($tournament->fresh()->matches()->count())->toBe(2);
});

it('uploads groups from a CSV via the modal action', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);

    $csv = "Gruppe A;Gruppe B\nT1;T2\nT3;T4";

    Livewire::test('pages::admin-setup')
        ->set('csvContent', $csv)
        ->call('uploadGroups')
        ->assertHasNoErrors()
        ->assertRedirect(route('matches.index'));

    expect($tournament->fresh()->status)->toBe('group')
        ->and($tournament->fresh()->groups()->count())->toBe(2)
        ->and($tournament->fresh()->teams()->count())->toBe(4);
});

it('validates that csvContent is provided before uploading groups', function () {
    Tournament::factory()->create();

    Livewire::test('pages::admin-setup')
        ->set('csvContent', '')
        ->call('uploadGroups')
        ->assertHasErrors(['csvContent' => 'required']);
});

it('resets a running tournament back to setup', function () {
    $tournament = Tournament::factory()->create(['status' => 'group']);
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);

    Livewire::test('pages::admin-setup')
        ->call('resetTournament');

    expect($tournament->fresh()->status)->toBe('setup')
        ->and($tournament->fresh()->matches()->count())->toBe(0)
        ->and($tournament->fresh()->groups()->count())->toBe(0)
        ->and($tournament->fresh()->tables()->count())->toBe(2)
        ->and($tournament->fresh()->teams()->count())->toBe(4);

    // Tables + teams survive a reset.
});
