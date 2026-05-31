<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use App\Services\KoBracketService;
use App\Services\MatchResultService;

function finishAllGroupMatches(Tournament $tournament): void
{
    $service = app(MatchResultService::class);
    foreach ($tournament->matches()->where('phase', 'group')->get() as $i => $match) {
        $service->startMatch($match, [
            'home_throws' => 5,
            'home_penalty_cups' => 0,
            'away_throws' => 5,
            'away_penalty_cups' => 0,
        ]);
        $service->endTimer($match->fresh());
        $service->saveResult($match->fresh(), 6, $i % 3); // varied scores so no draws.
    }
}

it('reports group phase incomplete until every group match is finished', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());

    expect(app(KoBracketService::class)->isGroupPhaseComplete($tournament->fresh()))->toBeFalse();

    finishAllGroupMatches($tournament->fresh());

    expect(app(KoBracketService::class)->isGroupPhaseComplete($tournament->fresh()))->toBeTrue();
});

it('generates the first KO round with cross-bracket matchups', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishAllGroupMatches($tournament->fresh());

    app(KoBracketService::class)->startKoPhase($tournament->fresh());

    $tournament = $tournament->fresh();

    expect($tournament->status)->toBe('ko')
        ->and($tournament->matches()->where('phase', 'ko')->count())->toBe(2);
});

it('advances winners to the next round and marks the tournament finished after the final', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishAllGroupMatches($tournament->fresh());

    app(KoBracketService::class)->startKoPhase($tournament->fresh());

    $service = app(MatchResultService::class);
    foreach ($tournament->matches()->where('phase', 'ko')->where('ko_round', 2)->get() as $match) {
        $service->startMatch($match, [
            'home_throws' => 5,
            'home_penalty_cups' => 0,
            'away_throws' => 5,
            'away_penalty_cups' => 0,
        ]);
        $service->endTimer($match->fresh());
        $service->saveResult($match->fresh(), 6, 2);
    }

    // Now there should be a final (ko_round = 1) with both teams set.
    $final = $tournament->matches()->where('ko_round', 1)->first();
    expect($final)->not->toBeNull()
        ->and($final->home_team_id)->not->toBeNull()
        ->and($final->away_team_id)->not->toBeNull();

    $service->startMatch($final, [
        'home_throws' => 5,
        'home_penalty_cups' => 0,
        'away_throws' => 5,
        'away_penalty_cups' => 0,
    ]);
    $service->endTimer($final->fresh());
    $service->saveResult($final->fresh(), 6, 1);

    expect($tournament->fresh()->status)->toBe('finished');
});
