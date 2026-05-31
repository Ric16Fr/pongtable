<?php

use App\Models\GameMatch;
use App\Models\MatchStat;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\StatisticsService;

function makeFinishedMatch(Tournament $tournament, Team $home, Team $away, array $stats, int $winnerId, string $phase = 'group', ?int $koRound = null): GameMatch
{
    $table = $tournament->tables()->first() ?? Table::factory()->create(['tournament_id' => $tournament->id]);

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
        'match_id' => $match->id,
        'team_id' => $home->id,
        'cups_scored' => $stats['home_cups'],
        'throws' => $stats['home_throws'],
        'penalty_cups' => $stats['home_penalty'] ?? 0,
        'duration_seconds' => $stats['duration'] ?? null,
    ]);

    MatchStat::create([
        'match_id' => $match->id,
        'team_id' => $away->id,
        'cups_scored' => $stats['away_cups'],
        'throws' => $stats['away_throws'],
        'penalty_cups' => $stats['away_penalty'] ?? 0,
        'duration_seconds' => $stats['duration'] ?? null,
    ]);

    return $match;
}

beforeEach(function () {
    $this->tournament = Tournament::factory()->create();
    Table::factory()->create(['tournament_id' => $this->tournament->id]);

    $this->shotgun = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Team Shotgun']);
    $this->bierherz = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Team Bierherz']);
    $this->nullp = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Team NullPointer']);
});

it('returns empty stats when no matches are finished', function () {
    $summary = app(StatisticsService::class)->summary($this->tournament);

    expect($summary['champion'])->toBeNull()
        ->and($summary['total_cups'])->toBe(0);
});

it('identifies the champion from the final', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 4, 'home_throws' => 10, 'away_throws' => 12,
    ], $this->shotgun->id, 'ko', 1);

    $this->tournament->update(['status' => 'finished']);

    expect(app(StatisticsService::class)->summary($this->tournament)['champion']['name'])
        ->toBe('Team Shotgun');
});

it('returns null champion when tournament is not finished', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 4, 'home_throws' => 10, 'away_throws' => 12,
    ], $this->shotgun->id, 'ko', 1);

    expect(app(StatisticsService::class)->summary($this->tournament)['champion'])->toBeNull();
});

it('picks the highest hit rate for sharpest shooter', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 8, 'away_cups' => 2, 'home_throws' => 10, 'away_throws' => 10,
    ], $this->shotgun->id);

    $summary = app(StatisticsService::class)->summary($this->tournament);

    expect($summary['sharpest_shooter']['team'])->toBe('Team Shotgun')
        ->and($summary['sharpest_shooter']['rate'])->toBe(80.0)
        ->and($summary['water_spitter']['team'])->toBe('Team Bierherz')
        ->and($summary['water_spitter']['rate'])->toBe(20.0);
});

it('finds the blitz win (shortest winning group match)', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 2, 'home_throws' => 8, 'away_throws' => 8, 'duration' => 600,
    ], $this->shotgun->id);

    makeFinishedMatch($this->tournament, $this->nullp, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 1, 'home_throws' => 7, 'away_throws' => 7, 'duration' => 180,
    ], $this->nullp->id);

    $summary = app(StatisticsService::class)->summary($this->tournament);

    expect($summary['blitz_win']['team'])->toBe('Team NullPointer')
        ->and($summary['blitz_win']['duration'])->toBe(180);
});

it('finds the marathon match (longest)', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 5, 'home_throws' => 8, 'away_throws' => 8, 'duration' => 900,
    ], $this->shotgun->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['marathon']['duration'])->toBe(900);
});

it('finds the cup emperor (most cups in a single match)', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 10, 'away_cups' => 3, 'home_throws' => 12, 'away_throws' => 12,
    ], $this->shotgun->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['cup_emperor']['team'])->toBe('Team Shotgun')
        ->and(app(StatisticsService::class)->summary($this->tournament)['cup_emperor']['cups'])->toBe(10);
});

it('aggregates penalty cups across all matches', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 3, 'home_throws' => 8, 'away_throws' => 8,
        'home_penalty' => 0, 'away_penalty' => 3,
    ], $this->shotgun->id);

    makeFinishedMatch($this->tournament, $this->bierherz, $this->nullp, [
        'home_cups' => 4, 'away_cups' => 5, 'home_throws' => 8, 'away_throws' => 8,
        'home_penalty' => 2, 'away_penalty' => 0,
    ], $this->nullp->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['penalty_magnet']['team'])->toBe('Team Bierherz')
        ->and(app(StatisticsService::class)->summary($this->tournament)['penalty_magnet']['penalty_cups'])->toBe(5);
});

it('computes efficiency as (cups - penalty) / throws', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 8, 'away_cups' => 4, 'home_throws' => 10, 'away_throws' => 10,
        'home_penalty' => 2, 'away_penalty' => 0,
    ], $this->shotgun->id);

    $efficiency = app(StatisticsService::class)->summary($this->tournament)['efficiency'];

    // Shotgun: (8-2)/10 = 60.0, Bierherz: (4-0)/10 = 40.0 → Shotgun wins
    expect($efficiency['team'])->toBe('Team Shotgun')
        ->and($efficiency['rate'])->toBe(60.0);
});

it('counts matches per team for most-played', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, ['home_cups' => 6, 'away_cups' => 2, 'home_throws' => 8, 'away_throws' => 8], $this->shotgun->id);
    makeFinishedMatch($this->tournament, $this->shotgun, $this->nullp, ['home_cups' => 6, 'away_cups' => 3, 'home_throws' => 8, 'away_throws' => 8], $this->shotgun->id);
    makeFinishedMatch($this->tournament, $this->bierherz, $this->nullp, ['home_cups' => 4, 'away_cups' => 5, 'home_throws' => 8, 'away_throws' => 8], $this->nullp->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['most_played']['team'])->toBe('Team Shotgun')
        ->and(app(StatisticsService::class)->summary($this->tournament)['most_played']['matches'])->toBe(2);
});

it('sums every cup ever scored', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 3, 'home_throws' => 8, 'away_throws' => 8,
    ], $this->shotgun->id);

    makeFinishedMatch($this->tournament, $this->nullp, $this->bierherz, [
        'home_cups' => 5, 'away_cups' => 2, 'home_throws' => 8, 'away_throws' => 8,
    ], $this->nullp->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['total_cups'])->toBe(16);
});
