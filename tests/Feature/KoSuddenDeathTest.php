<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\MatchResultService;

/**
 * Build a finished-ready KO final between two fresh teams.
 *
 * @param  array<string, mixed>  $tournamentOverrides
 */
function koFinal(array $tournamentOverrides = []): GameMatch
{
    $tournament = Tournament::factory()->create($tournamentOverrides);
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Home']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Away']);

    return GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'ko',
        'ko_round' => 1,
        'ko_position' => 0,
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'pending',
    ]);
}

it('lets the referee decide a tied KO match when sudden death is on', function () {
    $match = koFinal(['ko_sudden_death' => true]);
    $svc = app(MatchResultService::class);

    // Home has fewer throws — the automatic tiebreaker would hand HOME the win.
    $svc->startMatch($match, [
        'home_throws' => 5, 'home_penalty_cups' => 0,
        'away_throws' => 9, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($match->fresh());

    // Referee picks AWAY as the sudden-death winner.
    $svc->saveResult($match->fresh(), 6, 6, $match->away_team_id);

    expect($match->fresh()->winner_team_id)->toBe($match->away_team_id);
});

it('ignores the sudden-death pick when the rule is off', function () {
    $match = koFinal(['ko_sudden_death' => false]);
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 5, 'home_penalty_cups' => 0,
        'away_throws' => 9, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($match->fresh());

    // Even though AWAY is passed, the automatic tiebreaker (fewer throws) wins.
    $svc->saveResult($match->fresh(), 6, 6, $match->away_team_id);

    expect($match->fresh()->winner_team_id)->toBe($match->home_team_id);
});

it('does not apply sudden death when the KO match is not tied', function () {
    $match = koFinal(['ko_sudden_death' => true]);
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 5, 'home_penalty_cups' => 0,
        'away_throws' => 9, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($match->fresh());

    // Away scored more cups — the sudden-death pick of HOME must be ignored.
    $svc->saveResult($match->fresh(), 6, 8, $match->home_team_id);

    expect($match->fresh()->winner_team_id)->toBe($match->away_team_id);
});

it('falls back to the automatic tiebreaker when no winner is selected', function () {
    $match = koFinal(['ko_sudden_death' => true]);
    $svc = app(MatchResultService::class);

    $svc->startMatch($match, [
        'home_throws' => 9, 'home_penalty_cups' => 0,
        'away_throws' => 5, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($match->fresh());

    // Tied, rule on, but no referee pick → fewer throws (AWAY) wins.
    $svc->saveResult($match->fresh(), 6, 6, null);

    expect($match->fresh()->winner_team_id)->toBe($match->away_team_id);
});
