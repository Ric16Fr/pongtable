<?php

use App\Models\Tournament;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GroupPhaseSeeder;
use Database\Seeders\KOPhaseSeeder;

function currentTournament(): Tournament
{
    return Tournament::where('name', 'Bierpong WM '.now()->year)->sole();
}

function archivedTournament(): Tournament
{
    return Tournament::where('name', 'Bierpong WM '.(now()->year - 1))->sole();
}

it('seeds an archived tournament that is fully played out', function () {
    $this->seed(DatabaseSeeder::class);

    $archive = archivedTournament();

    expect($archive->status)->toBe('finished')
        ->and($archive->teams()->count())->toBe(16)
        ->and($archive->tables()->count())->toBe(4)
        ->and($archive->matches()->where('status', '!=', 'finished')->count())->toBe(0);

    // A champion exists: the final (ko_round 1) is finished with a winner.
    $final = $archive->matches()->where('phase', 'ko')->where('ko_round', 1)->sole();
    expect($final->status)->toBe('finished')
        ->and($final->winner_team_id)->not->toBeNull();
});

it('seeds the current tournament in setup with teams but no groups', function () {
    $this->seed(DatabaseSeeder::class);

    $tournament = currentTournament();

    expect($tournament->status)->toBe('setup')
        ->and($tournament->teams()->count())->toBe(16)
        ->and($tournament->tables()->count())->toBe(4)
        ->and($tournament->groups()->count())->toBe(0)
        ->and($tournament->matches()->count())->toBe(0);
});

it('seeds the current tournament mid group phase with finished and open matches', function () {
    $this->seed(GroupPhaseSeeder::class);

    $tournament = currentTournament();

    expect($tournament->status)->toBe('group')
        ->and($tournament->groups()->count())->toBe(4);

    $groupMatches = $tournament->matches()->where('phase', 'group');
    expect($groupMatches->count())->toBe(24)
        ->and((clone $groupMatches)->where('status', 'finished')->count())->toBe(16)
        ->and((clone $groupMatches)->where('status', 'pending')->count())->toBe(8);

    // Finished matches fed into the group standings.
    expect($tournament->groups()->first()->teams()->sum('points'))->toBeGreaterThan(0);

    expect(archivedTournament()->status)->toBe('finished');
});

it('seeds the current tournament mid KO phase with open semi-finals', function () {
    $this->seed(KOPhaseSeeder::class);

    $tournament = currentTournament();

    expect($tournament->status)->toBe('ko')
        ->and($tournament->matches()->where('phase', 'group')->where('status', '!=', 'finished')->count())->toBe(0);

    // Quarter-finals (4 teams per side → ko_round 4) all finished.
    $quarterFinals = $tournament->matches()->where('phase', 'ko')->where('ko_round', 4)->get();
    expect($quarterFinals)->toHaveCount(4)
        ->and($quarterFinals->every(fn ($match) => $match->status === 'finished'))->toBeTrue();

    // Semi-finals seeded with both teams, but still open.
    $semiFinals = $tournament->matches()->where('phase', 'ko')->where('ko_round', 2)->get();
    expect($semiFinals)->toHaveCount(2)
        ->and($semiFinals->every(fn ($match) => $match->status === 'pending'))->toBeTrue()
        ->and($semiFinals->every(fn ($match) => $match->home_team_id && $match->away_team_id))->toBeTrue();

    expect(archivedTournament()->status)->toBe('finished');
});
