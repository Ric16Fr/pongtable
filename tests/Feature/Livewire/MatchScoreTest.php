<?php

use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->referee()->create());
});

function freshMatch(string $status = 'pending'): GameMatch
{
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $group = Group::create([
        'tournament_id' => $tournament->id,
        'table_id' => $table->id,
        'name' => 'Gruppe A',
    ]);

    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Home Team']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Away Team']);
    $group->teams()->attach([$home->id, $away->id]);

    return GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'group',
        'group_id' => $group->id,
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => $status,
    ]);
}

it('mounting a pending match advances it to pre_entry', function () {
    $match = freshMatch();

    Livewire::test('pages::match-score', ['match' => $match]);

    expect($match->fresh()->status)->toBe('pre_entry');
});

it('defaults cup inputs to 10:10 for non-finished matches', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->assertSet('homeCups', 10)
        ->assertSet('awayCups', 10);
});

it('keeps actual cup values for finished matches', function () {
    $match = freshMatch('finished');
    $match->stats()->create([
        'team_id' => $match->home_team_id,
        'cups_scored' => 6, 'throws' => 5, 'penalty_cups' => 0,
    ]);
    $match->stats()->create([
        'team_id' => $match->away_team_id,
        'cups_scored' => 4, 'throws' => 7, 'penalty_cups' => 1,
    ]);

    Livewire::test('pages::match-score', ['match' => $match])
        ->assertSet('homeCups', 6)
        ->assertSet('awayCups', 4);
});

it('initializes form fields from existing stats', function () {
    $match = freshMatch('pre_entry');
    $match->stats()->create([
        'team_id' => $match->home_team_id,
        'throws' => 7, 'penalty_cups' => 2, 'cups_scored' => 0,
    ]);

    Livewire::test('pages::match-score', ['match' => $match])
        ->assertSet('homeThrows', 7)
        ->assertSet('homePenalty', 2);
});

it('adjust() never goes below zero', function () {
    $match = freshMatch();

    Livewire::test('pages::match-score', ['match' => $match])
        ->set('homeThrows', 0)
        ->call('adjust', 'homeThrows', -1)
        ->assertSet('homeThrows', 0);
});

it('startMatch activates the match with empty stats (counts happen live)', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch');

    $match->refresh();
    expect($match->status)->toBe('active')
        ->and($match->started_at)->not->toBeNull();

    $homeStat = $match->stats->firstWhere('team_id', $match->home_team_id);
    expect($homeStat->throws)->toBe(0)
        ->and($homeStat->penalty_cups)->toBe(0);
});

it('persists throws and penalties entered during the active phase', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->set('homeThrows', 6)
        ->set('homePenalty', 1)
        ->set('awayThrows', 5)
        ->set('awayPenalty', 2);

    $match->refresh();
    $homeStat = $match->stats->firstWhere('team_id', $match->home_team_id);
    $awayStat = $match->stats->firstWhere('team_id', $match->away_team_id);
    expect($homeStat->throws)->toBe(6)
        ->and($homeStat->penalty_cups)->toBe(1)
        ->and($awayStat->throws)->toBe(5)
        ->and($awayStat->penalty_cups)->toBe(2);
});

it('persists throws and penalties when adjusted via +/- during active phase', function () {
    // Regression: Livewire `updated*` hooks only fire for client-driven changes
    // (wire:model). The +/- buttons go through `adjust()` which mutates state
    // server-side, so persistence must happen there too.
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->call('adjust', 'homeThrows', 1)
        ->call('adjust', 'homeThrows', 1)
        ->call('adjust', 'homeThrows', 1)
        ->call('adjust', 'homePenalty', 1)
        ->call('adjust', 'awayPenalty', 1)
        ->call('adjust', 'awayPenalty', 1);

    $homeStat = $match->fresh()->stats->firstWhere('team_id', $match->home_team_id);
    $awayStat = $match->fresh()->stats->firstWhere('team_id', $match->away_team_id);

    expect($homeStat->throws)->toBe(3)
        ->and($homeStat->penalty_cups)->toBe(1)
        ->and($awayStat->penalty_cups)->toBe(2);
});

it('does not auto-advance KO matches with an unresolved opponent', function () {
    $match = freshMatch();
    $match->update(['away_team_id' => null, 'phase' => 'ko', 'ko_round' => 2]);

    Livewire::test('pages::match-score', ['match' => $match])
        ->assertSee('Wartet auf Vorrunde')
        ->assertDontSee('Spiel starten');

    expect($match->fresh()->status)->toBe('pending');
});

it('startMatch is a no-op when an opponent is missing', function () {
    $match = freshMatch('pre_entry');
    $match->update(['away_team_id' => null, 'phase' => 'ko', 'ko_round' => 2]);

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch');

    expect($match->fresh()->status)->toBe('pre_entry')
        ->and($match->stats()->count())->toBe(0);
});

it('does not persist throw edits while still in pre_entry', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->set('homeThrows', 99);

    $homeStat = $match->fresh()->stats->firstWhere('team_id', $match->home_team_id);
    expect($homeStat?->throws ?? 0)->toBe(0);
});

it('endTimer transitions active → scoring', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->set('homeThrows', 5)->set('awayThrows', 5)
        ->call('endTimer');

    expect($match->fresh()->status)->toBe('scoring');
});

it('saveResult writes cups, picks the winner, and finishes the match', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->set('homeThrows', 5)->set('awayThrows', 5)
        ->call('endTimer')
        ->set('homeCups', 6)
        ->set('awayCups', 4)
        ->call('saveResult');

    $match->refresh();
    expect($match->status)->toBe('finished')
        ->and($match->winner_team_id)->toBe($match->home_team_id);

    $home = $match->stats->firstWhere('team_id', $match->home_team_id);
    expect($home->cups_scored)->toBe(6);
});

it('rejects cup values outside 0-30', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->set('homeThrows', 5)->set('awayThrows', 5)
        ->call('endTimer')
        ->set('homeCups', 999)
        ->set('awayCups', 4)
        ->call('saveResult')
        ->assertHasErrors(['homeCups']);
});

it('renders the finished view for a completed match', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->set('homeThrows', 5)->set('awayThrows', 5)
        ->call('endTimer')
        ->set('homeCups', 6)->set('awayCups', 4)
        ->call('saveResult')
        ->assertSee('Sieger')
        ->assertSeeInOrder(['>6<', '>4<']);
});

function koScoringMatch(bool $suddenDeath): GameMatch
{
    $tournament = Tournament::factory()->create(['ko_sudden_death' => $suddenDeath]);
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Home Team']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Away Team']);

    return GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'ko',
        'ko_round' => 1,
        'ko_position' => 0,
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'pre_entry',
    ]);
}

it('blocks saving a tied KO match until a sudden-death winner is picked', function () {
    $match = koScoringMatch(suddenDeath: true);

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->call('endTimer')
        ->set('homeCups', 6)->set('awayCups', 6)
        ->call('saveResult')
        ->assertHasErrors(['suddenDeathWinner']);

    expect($match->fresh()->status)->toBe('scoring');
});

it('finishes a tied KO match with the referee-selected sudden-death winner', function () {
    $match = koScoringMatch(suddenDeath: true);

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->call('endTimer')
        ->set('homeCups', 6)->set('awayCups', 6)
        ->set('suddenDeathWinner', $match->away_team_id)
        ->call('saveResult')
        ->assertHasNoErrors();

    expect($match->fresh()->status)->toBe('finished')
        ->and($match->fresh()->winner_team_id)->toBe($match->away_team_id);
});

it('shows the sudden-death selector only when a KO match is tied', function () {
    $match = koScoringMatch(suddenDeath: true);

    $component = Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->call('endTimer')
        ->set('homeCups', 6)->set('awayCups', 4);

    $component->assertDontSee('Sudden Death');

    $component->set('awayCups', 6)
        ->assertSee('Sudden Death');
});

it('auto-resolves a tied KO match when sudden death is off', function () {
    $match = koScoringMatch(suddenDeath: false);

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->set('homeThrows', 5)->set('awayThrows', 9)
        ->call('endTimer')
        ->set('homeCups', 6)->set('awayCups', 6)
        ->call('saveResult')
        ->assertHasNoErrors();

    // Fewer throws (home) wins automatically — no referee prompt.
    expect($match->fresh()->winner_team_id)->toBe($match->home_team_id);
});

it('shows the throw counter during an active match by default', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->assertSee('Würfe')
        ->assertSee('Strafe');
});

it('hides the throw counter while a match is active when count_throws is off', function () {
    $match = freshMatch('pre_entry');
    $match->tournament->update(['count_throws' => false]);

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->assertDontSee('Würfe')
        ->assertSee('Strafe');
});

it('hides the Würfe row in the finished summary when count_throws is off', function () {
    $match = freshMatch('pre_entry');
    $match->tournament->update(['count_throws' => false]);

    Livewire::test('pages::match-score', ['match' => $match])
        ->call('startMatch')
        ->call('endTimer')
        ->set('homeCups', 6)->set('awayCups', 4)
        ->call('saveResult')
        ->assertSee('Sieger')
        ->assertDontSee('Würfe');
});
