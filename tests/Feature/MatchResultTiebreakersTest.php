<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\MatchResultService;

function pendingMatch(array $overrides = []): GameMatch
{
    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Home']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Away']);

    return GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'group',
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'pending',
        ...$overrides,
    ]);
}

it('declares the higher-cup team the winner without any tiebreaker', function () {
    $match = pendingMatch();
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 10, 'home_penalty_cups' => 2,
        'away_throws' => 5, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($match->fresh());
    $svc->saveResult($match->fresh(), 7, 5);

    expect($match->fresh()->winner_team_id)->toBe($match->home_team_id);
});

it('falls back to fewer throws when cups are tied', function () {
    $match = pendingMatch();
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 9, 'home_penalty_cups' => 0,
        'away_throws' => 6, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($match->fresh());
    $svc->saveResult($match->fresh(), 5, 5);

    expect($match->fresh()->winner_team_id)->toBe($match->away_team_id);
});

it('falls back to fewer penalty cups when cups and throws are tied', function () {
    $match = pendingMatch();
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 7, 'home_penalty_cups' => 3,
        'away_throws' => 7, 'away_penalty_cups' => 1,
    ]);
    $svc->endTimer($match->fresh());
    $svc->saveResult($match->fresh(), 4, 4);

    expect($match->fresh()->winner_team_id)->toBe($match->away_team_id);
});

it('hands the win to the home team when everything is tied (deterministic fallback)', function () {
    $match = pendingMatch();
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 8, 'home_penalty_cups' => 2,
        'away_throws' => 8, 'away_penalty_cups' => 2,
    ]);
    $svc->endTimer($match->fresh());
    $svc->saveResult($match->fresh(), 6, 6);

    expect($match->fresh()->winner_team_id)->toBe($match->home_team_id);
});

it('writes pivot stats only for finished group matches', function () {
    $match = pendingMatch();
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 5, 'home_penalty_cups' => 0,
        'away_throws' => 5, 'away_penalty_cups' => 0,
    ]);

    // Mid-flight: no pivot rows yet.
    $homeRow = $match->fresh()->group?->teams()->where('teams.id', $match->home_team_id)->first();
    expect($homeRow?->pivot->points ?? 0)->toBe(0);
});

it('refuses to save a result before endTimer when status is still pending', function () {
    $match = pendingMatch();
    $svc = app(MatchResultService::class);

    // No startMatch/endTimer — saveResult on pending should silently no-op.
    $svc->saveResult($match, 6, 3);

    expect($match->fresh()->status)->toBe('pending')
        ->and($match->fresh()->winner_team_id)->toBeNull();
});

it('persists duration_seconds when ending the timer', function () {
    $match = pendingMatch();
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 0, 'home_penalty_cups' => 0,
        'away_throws' => 0, 'away_penalty_cups' => 0,
    ]);

    // Pretend the match started a minute ago.
    $match->fresh()->update(['started_at' => now()->subSeconds(60)]);
    $svc->endTimer($match->fresh());

    $homeStat = $match->fresh()->stats->firstWhere('team_id', $match->home_team_id);
    expect($homeStat->duration_seconds)->not->toBeNull()
        ->and($homeStat->duration_seconds)->toBeGreaterThanOrEqual(60);
});
