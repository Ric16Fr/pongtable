<?php

use App\Models\Group;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use Livewire\Livewire;

it('shows the empty state when no groups exist', function () {
    $tournament = Tournament::factory()->create();

    Livewire::test('pages::leaderboard', ['tournamentId' => $tournament->id])
        ->assertSee('Noch ruhig hier');
});

it('orders teams by points first, then by cup difference, then by cups scored', function () {
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $group = Group::create([
        'tournament_id' => $tournament->id,
        'table_id' => $table->id,
        'name' => 'Gruppe A',
    ]);

    $leader = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Leader']);
    $mid = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Mid']);
    $last = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Last']);

    $group->teams()->attach($leader->id, ['points' => 6, 'wins' => 2, 'cups_scored_total' => 12, 'cups_conceded_total' => 4]);
    $group->teams()->attach($mid->id, ['points' => 3, 'wins' => 1, 'losses' => 1, 'cups_scored_total' => 8, 'cups_conceded_total' => 8]);
    $group->teams()->attach($last->id, ['points' => 0, 'losses' => 2, 'cups_scored_total' => 4, 'cups_conceded_total' => 12]);

    Livewire::test('pages::leaderboard', ['tournamentId' => $tournament->id])
        ->assertSeeInOrder(['Team Leader', 'Team Mid', 'Team Last']);
});

it('uses cup difference as the first tiebreaker', function () {
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $group = Group::create([
        'tournament_id' => $tournament->id,
        'table_id' => $table->id,
        'name' => 'Gruppe A',
    ]);

    $better = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Better Diff']);
    $worse = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Worse Diff']);

    // Same points, different cup diff.
    $group->teams()->attach($worse->id, ['points' => 3, 'wins' => 1, 'cups_scored_total' => 5, 'cups_conceded_total' => 5]);
    $group->teams()->attach($better->id, ['points' => 3, 'wins' => 1, 'cups_scored_total' => 6, 'cups_conceded_total' => 3]);

    Livewire::test('pages::leaderboard', ['tournamentId' => $tournament->id])
        ->assertSeeInOrder(['Better Diff', 'Worse Diff']);
});
