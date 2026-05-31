<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use App\Services\MatchResultService;

function buildGroupPhase(): Tournament
{
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());

    return $tournament->fresh();
}

it('walks a group match through pre_entry → active → scoring → finished', function () {
    $tournament = buildGroupPhase();
    $match = $tournament->matches()->first();
    $service = app(MatchResultService::class);

    $service->startPreEntry($match);
    expect($match->fresh()->status)->toBe('pre_entry');

    $service->startMatch($match->fresh(), [
        'home_throws' => 5,
        'home_penalty_cups' => 1,
        'away_throws' => 6,
        'away_penalty_cups' => 0,
    ]);
    $match = $match->fresh();

    expect($match->status)->toBe('active')
        ->and($match->started_at)->not->toBeNull()
        ->and($match->stats()->count())->toBe(2);

    $service->endTimer($match);
    expect($match->fresh()->status)->toBe('scoring');

    $service->saveResult($match->fresh(), 6, 4);
    $match = $match->fresh();

    expect($match->status)->toBe('finished')
        ->and($match->winner_team_id)->toBe($match->home_team_id);

    $home = $match->stats()->where('team_id', $match->home_team_id)->first();
    expect($home->cups_scored)->toBe(6);

    $homeGroup = $match->group->teams()->where('teams.id', $match->home_team_id)->first();
    expect($homeGroup->pivot->points)->toBe(3)
        ->and($homeGroup->pivot->wins)->toBe(1)
        ->and($homeGroup->pivot->cups_scored_total)->toBe(6)
        ->and($homeGroup->pivot->cups_conceded_total)->toBe(4);
});

it('uses fewer throws as the first tiebreaker', function () {
    $tournament = buildGroupPhase();
    $match = $tournament->matches()->first();
    $service = app(MatchResultService::class);

    $service->startMatch($match, [
        'home_throws' => 7,
        'home_penalty_cups' => 0,
        'away_throws' => 5,
        'away_penalty_cups' => 0,
    ]);
    $service->endTimer($match->fresh());
    $service->saveResult($match->fresh(), 5, 5);

    expect($match->fresh()->winner_team_id)->toBe($match->away_team_id);
});

it('falls back to fewer penalty cups when cups and throws tie', function () {
    $tournament = buildGroupPhase();
    $match = $tournament->matches()->first();
    $service = app(MatchResultService::class);

    $service->startMatch($match, [
        'home_throws' => 6,
        'home_penalty_cups' => 2,
        'away_throws' => 6,
        'away_penalty_cups' => 0,
    ]);
    $service->endTimer($match->fresh());
    $service->saveResult($match->fresh(), 4, 4);

    expect($match->fresh()->winner_team_id)->toBe($match->away_team_id);
});

it('ignores out-of-order calls', function () {
    $match = GameMatch::factory()->create(['status' => 'pending']);
    $service = app(MatchResultService::class);

    // endTimer on a pending match is a no-op.
    $service->endTimer($match);
    expect($match->fresh()->status)->toBe('pending');
});
