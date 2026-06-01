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
    $match = freshMatch('pending');

    Livewire::test('pages::match-score', ['match' => $match]);

    expect($match->fresh()->status)->toBe('pre_entry');
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

it('startMatch persists stats and activates the match', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->set('homeThrows', 6)
        ->set('homePenalty', 1)
        ->set('awayThrows', 5)
        ->set('awayPenalty', 0)
        ->call('startMatch');

    $match->refresh();
    expect($match->status)->toBe('active')
        ->and($match->started_at)->not->toBeNull();

    $homeStat = $match->stats->firstWhere('team_id', $match->home_team_id);
    expect($homeStat->throws)->toBe(6)
        ->and($homeStat->penalty_cups)->toBe(1);
});

it('endTimer transitions active → scoring', function () {
    $match = freshMatch('pre_entry');

    $component = Livewire::test('pages::match-score', ['match' => $match])
        ->set('homeThrows', 5)->set('awayThrows', 5)
        ->call('startMatch')
        ->call('endTimer');

    expect($match->fresh()->status)->toBe('scoring');
});

it('saveResult writes cups, picks the winner, and finishes the match', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->set('homeThrows', 5)->set('awayThrows', 5)
        ->call('startMatch')
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
        ->set('homeThrows', 5)->set('awayThrows', 5)
        ->call('startMatch')
        ->call('endTimer')
        ->set('homeCups', 999)
        ->set('awayCups', 4)
        ->call('saveResult')
        ->assertHasErrors(['homeCups']);
});

it('renders the finished view for a completed match', function () {
    $match = freshMatch('pre_entry');

    Livewire::test('pages::match-score', ['match' => $match])
        ->set('homeThrows', 5)->set('awayThrows', 5)
        ->call('startMatch')
        ->call('endTimer')
        ->set('homeCups', 6)->set('awayCups', 4)
        ->call('saveResult')
        ->assertSee('Sieger')
        ->assertSeeInOrder(['>6<', '>4<']);
});
