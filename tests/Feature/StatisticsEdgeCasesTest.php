<?php

use App\Models\GameMatch;
use App\Models\MatchStat;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\StatisticsService;

function freshTournamentWithThreeTeams(): array
{
    $tournament = Tournament::factory()->create();
    Table::factory()->create(['tournament_id' => $tournament->id]);

    $a = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Alpha']);
    $b = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Bravo']);
    $c = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Charlie']);

    return [$tournament, $a, $b, $c];
}

function recordFinishedMatch(
    Tournament $tournament,
    Team $home,
    Team $away,
    array $stats,
    int $winnerId,
    string $phase = 'group',
    ?int $koRound = null,
): GameMatch {
    $table = $tournament->tables()->first();

    $match = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => $phase,
        'ko_round' => $koRound,
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'winner_team_id' => $winnerId,
        'status' => 'finished',
    ]);

    MatchStat::create([
        'match_id' => $match->id, 'team_id' => $home->id,
        'cups_scored' => $stats['home_cups'], 'throws' => $stats['home_throws'],
        'penalty_cups' => $stats['home_penalty'] ?? 0,
        'duration_seconds' => $stats['duration'] ?? null,
    ]);
    MatchStat::create([
        'match_id' => $match->id, 'team_id' => $away->id,
        'cups_scored' => $stats['away_cups'], 'throws' => $stats['away_throws'],
        'penalty_cups' => $stats['away_penalty'] ?? 0,
        'duration_seconds' => $stats['duration'] ?? null,
    ]);

    return $match;
}

it('returns sharpest_shooter as null when nobody has thrown', function () {
    [$tournament, $a, $b] = freshTournamentWithThreeTeams();

    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 0, 'away_cups' => 0,
        'home_throws' => 0, 'away_throws' => 0,
    ], $a->id);

    $summary = app(StatisticsService::class)->summary($tournament);

    expect($summary['sharpest_shooter'])->toBeNull()
        ->and($summary['water_spitter'])->toBeNull();
});

it('excludes teams with zero throws from shooter calculations', function () {
    [$tournament, $a, $b, $c] = freshTournamentWithThreeTeams();

    // Bravo: 2/10 = 20% spitter candidate.
    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 8, 'away_cups' => 2,
        'home_throws' => 10, 'away_throws' => 10,
    ], $a->id);

    // Charlie: 0 cups, 0 throws → must be excluded as "throws > 0" filter applies.
    recordFinishedMatch($tournament, $a, $c, [
        'home_cups' => 8, 'away_cups' => 0,
        'home_throws' => 10, 'away_throws' => 0,
    ], $a->id);

    $summary = app(StatisticsService::class)->summary($tournament);

    expect($summary['water_spitter']['team'])->toBe('Team Bravo');
});

it('excludes KO matches from the blitz win calculation', function () {
    [$tournament, $a, $b] = freshTournamentWithThreeTeams();

    // A super-short KO match — should NOT be picked.
    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 6, 'away_cups' => 1,
        'home_throws' => 7, 'away_throws' => 7,
        'duration' => 60,
    ], $a->id, phase: 'ko', koRound: 1);

    // A longer group match — this should win blitz.
    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 6, 'away_cups' => 2,
        'home_throws' => 8, 'away_throws' => 8,
        'duration' => 240,
    ], $a->id);

    $summary = app(StatisticsService::class)->summary($tournament);

    expect($summary['blitz_win']['duration'])->toBe(240);
});

it('counts every match for the marathon, even KO ones', function () {
    [$tournament, $a, $b] = freshTournamentWithThreeTeams();

    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 6, 'away_cups' => 5,
        'home_throws' => 8, 'away_throws' => 8,
        'duration' => 500,
    ], $a->id);

    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 6, 'away_cups' => 5,
        'home_throws' => 8, 'away_throws' => 8,
        'duration' => 1200,
    ], $a->id, phase: 'ko', koRound: 1);

    $summary = app(StatisticsService::class)->summary($tournament);

    expect($summary['marathon']['duration'])->toBe(1200);
});

it('reports a Patt in nail-biter when cups tied but winner was decided by throws', function () {
    [$tournament, $a, $b] = freshTournamentWithThreeTeams();

    // 6:6 final cups, A won by fewer throws.
    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 6, 'away_cups' => 6,
        'home_throws' => 7, 'away_throws' => 9,
    ], $a->id);

    $summary = app(StatisticsService::class)->summary($tournament);

    expect($summary['nail_biter']['diff'])->toBe(0);
});

it('selects efficiency over plain hit-rate when penalty cups differ', function () {
    [$tournament, $a, $b] = freshTournamentWithThreeTeams();

    // Alpha: 8 cups, 0 penalty, 10 throws → (8-0)/10 = 80% efficiency.
    // Bravo: 9 cups, 4 penalty, 10 throws → (9-4)/10 = 50% efficiency.
    // (Bravo has higher hit rate 90%, but worse efficiency.)
    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 8, 'away_cups' => 9,
        'home_throws' => 10, 'away_throws' => 10,
        'home_penalty' => 0, 'away_penalty' => 4,
    ], $b->id);

    $summary = app(StatisticsService::class)->summary($tournament);

    expect($summary['efficiency']['team'])->toBe('Team Alpha')
        ->and($summary['sharpest_shooter']['team'])->toBe('Team Bravo');
});

it('returns total_cups as 0 when no matches are finished', function () {
    [$tournament] = freshTournamentWithThreeTeams();

    expect(app(StatisticsService::class)->summary($tournament)['total_cups'])->toBe(0);
});

it('aggregates Schluck-Olymp across multiple matches per team', function () {
    [$tournament, $a, $b, $c] = freshTournamentWithThreeTeams();

    // Match 1: Alpha 10:5 Bravo.
    //   Alpha drinks 5 (during play).
    //   Bravo drinks 10 + 5 (10-5 diff as loser) = 15.
    recordFinishedMatch($tournament, $a, $b, [
        'home_cups' => 10, 'away_cups' => 5,
        'home_throws' => 12, 'away_throws' => 12,
    ], $a->id);

    // Match 2: Alpha 10:3 Charlie.
    //   Alpha drinks +3 → 8 total.
    //   Charlie drinks 10 + 7 = 17.
    recordFinishedMatch($tournament, $a, $c, [
        'home_cups' => 10, 'away_cups' => 3,
        'home_throws' => 12, 'away_throws' => 12,
    ], $a->id);

    $olymp = app(StatisticsService::class)->summary($tournament)['schluck_olymp'];

    expect($olymp['team'])->toBe('Team Charlie')
        ->and($olymp['cups'])->toBe(17);
});
