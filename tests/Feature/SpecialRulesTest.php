<?php

use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('auto-creates a tournament on first mount when none exists', function () {
    Livewire::test('pages::special-rules')
        ->assertOk();

    expect(Tournament::count())->toBe(1);
});

it('saves tournament settings', function () {
    Livewire::test('pages::special-rules')
        ->set('tournamentName', 'Neuer Cup')
        ->set('groupMinutes', 12)
        ->set('koMinutes', 20)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $t = Tournament::first();
    expect($t->name)->toBe('Neuer Cup')
        ->and($t->group_match_duration_minutes)->toBe(12)
        ->and($t->ko_match_duration_minutes)->toBe(20);
});

it('rejects a blank tournament name on save', function () {
    Livewire::test('pages::special-rules')
        ->set('tournamentName', '')
        ->call('saveSettings')
        ->assertHasErrors(['tournamentName' => 'required']);
});

it('rejects match durations out of 1-60 range', function () {
    Livewire::test('pages::special-rules')
        ->set('groupMinutes', 0)
        ->call('saveSettings')
        ->assertHasErrors(['groupMinutes']);

    Livewire::test('pages::special-rules')
        ->set('koMinutes', 999)
        ->call('saveSettings')
        ->assertHasErrors(['koMinutes']);
});

it('defaults the "Würfe zählen" switch to on', function () {
    Tournament::factory()->create();

    Livewire::test('pages::special-rules')
        ->assertSet('countThrows', true);
});

it('defaults the "Platzierungsspiele austragen" switch to off', function () {
    Tournament::factory()->create();

    Livewire::test('pages::special-rules')
        ->assertSet('playPlacementMatches', false);
});

it('persists the play_placement_matches toggle to the tournament', function () {
    $tournament = Tournament::factory()->create();

    Livewire::test('pages::special-rules')
        ->set('playPlacementMatches', true)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($tournament->fresh()->play_placement_matches)->toBeTrue();

    Livewire::test('pages::special-rules')
        ->set('playPlacementMatches', false)
        ->call('saveSettings');

    expect($tournament->fresh()->play_placement_matches)->toBeFalse();
});

it('persists the count_throws toggle to the tournament', function () {
    $tournament = Tournament::factory()->create();

    Livewire::test('pages::special-rules')
        ->set('countThrows', false)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($tournament->fresh()->count_throws)->toBeFalse();

    Livewire::test('pages::special-rules')
        ->set('countThrows', true)
        ->call('saveSettings');

    expect($tournament->fresh()->count_throws)->toBeTrue();
});
