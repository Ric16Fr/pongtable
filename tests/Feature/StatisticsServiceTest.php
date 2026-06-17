<?php

use App\Models\GameMatch;
use App\Models\MatchMemberCup;
use App\Models\MatchStat;
use App\Models\Table;
use App\Models\Team;
use App\Models\TeamMember;
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

it('finds the nail biter — closest match by cup difference', function () {
    // Two finished matches: one 10:5 (diff 5), one 10:9 (diff 1) → 10:9 wins.
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 10, 'away_cups' => 5, 'home_throws' => 12, 'away_throws' => 12,
    ], $this->shotgun->id);

    makeFinishedMatch($this->tournament, $this->nullp, $this->bierherz, [
        'home_cups' => 10, 'away_cups' => 9, 'home_throws' => 14, 'away_throws' => 14,
    ], $this->nullp->id);

    $nail = app(StatisticsService::class)->summary($this->tournament)['nail_biter'];

    // Winner team first, loser second; score reads winner:loser.
    expect($nail['teams'])->toBe(['Team NullPointer', 'Team Bierherz'])
        ->and($nail['score'])->toBe('10:9')
        ->and($nail['diff'])->toBe(1);
});

it('breaks nail-biter ties by total cups (more action wins)', function () {
    // Two matches both 1-cup diff: 7:6 vs 10:9 → 10:9 wins (19 > 13 total cups).
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 7, 'away_cups' => 6, 'home_throws' => 9, 'away_throws' => 9,
    ], $this->shotgun->id);

    makeFinishedMatch($this->tournament, $this->nullp, $this->bierherz, [
        'home_cups' => 10, 'away_cups' => 9, 'home_throws' => 14, 'away_throws' => 14,
    ], $this->nullp->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['nail_biter']['score'])->toBe('10:9');
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

it('hides penalty_magnet when no penalties were collected', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 3, 'home_throws' => 8, 'away_throws' => 8,
    ], $this->shotgun->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['penalty_magnet'])->toBeNull();
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

it('finds the Schluck-Olymp — team that drank the most over the tournament', function () {
    // Match 1: Shotgun 10:4 Bierherz. Bierherz collected 2 own penalty cups.
    //   Shotgun (winner): 4 (Bierherz cups drunk during play) + 0 penalty = 4
    //   Bierherz (loser): 10 (Shotgun cups drunk during play) + 2 penalty + 6 remaining (10-4) = 18
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 10, 'away_cups' => 4, 'home_throws' => 12, 'away_throws' => 12,
        'home_penalty' => 0, 'away_penalty' => 2,
    ], $this->shotgun->id);

    // Match 2: Bierherz 6:10 NullPointer.
    //   Bierherz (loser):     +10 drunk during play + 0 penalty + 4 remaining (10-6) = +14 → 32 total
    //   NullPointer (winner): +6 drunk during play + 0 penalty = +6 total
    makeFinishedMatch($this->tournament, $this->bierherz, $this->nullp, [
        'home_cups' => 6, 'away_cups' => 10, 'home_throws' => 12, 'away_throws' => 12,
    ], $this->nullp->id);

    $olymp = app(StatisticsService::class)->summary($this->tournament)['schluck_olymp'];

    expect($olymp['team'])->toBe('Team Bierherz')
        ->and($olymp['cups'])->toBe(32);
});

it('counts the winner-side remainder as extra drinks for the loser', function () {
    // Single match 10:7. Loser drank 10 during play + 0 penalty + 3 remaining = 13.
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 10, 'away_cups' => 7, 'home_throws' => 12, 'away_throws' => 12,
    ], $this->shotgun->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['schluck_olymp']['cups'])->toBe(13);
});

it('hides schluck_olymp when nobody drank a single cup', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 0, 'away_cups' => 0, 'home_throws' => 0, 'away_throws' => 0,
    ], $this->shotgun->id);

    expect(app(StatisticsService::class)->summary($this->tournament)['schluck_olymp'])->toBeNull();
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

it('hides throw-based stats when count_throws is disabled', function () {
    $this->tournament->update(['count_throws' => false]);

    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 8, 'away_cups' => 2, 'home_throws' => 10, 'away_throws' => 10,
        'home_penalty' => 1, 'away_penalty' => 2, 'duration' => 420,
    ], $this->shotgun->id);

    $summary = app(StatisticsService::class)->summary($this->tournament);

    // Throw-dependent stats vanish, but penalty/duration/cup-based ones stay.
    expect($summary['sharpest_shooter'])->toBeNull()
        ->and($summary['water_spitter'])->toBeNull()
        ->and($summary['efficiency'])->toBeNull()
        ->and($summary['penalty_magnet']['team'])->toBe('Team Bierherz')
        ->and($summary['blitz_win']['team'])->toBe('Team Shotgun')
        ->and($summary['schluck_olymp']['team'])->not->toBeNull();
});

it('exposes special awards consistent with the summary, excluding champion and totals', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 8, 'away_cups' => 2, 'home_throws' => 10, 'away_throws' => 10,
        'home_penalty' => 0, 'away_penalty' => 3,
    ], $this->shotgun->id);

    $service = app(StatisticsService::class);
    $summary = $service->summary($this->tournament);
    $awards = $service->awards($this->tournament);

    expect($awards)->not->toBeEmpty();

    foreach ($awards as $award) {
        expect($summary[$award['key']])->not->toBeNull();
        expect($award['subjects'])->not->toBeEmpty();
    }

    $keys = collect($awards)->pluck('key');
    expect($keys)->not->toContain('champion')
        ->and($keys)->not->toContain('total_cups');

    // A team prize carries its German label and the team as subject.
    $shooter = collect($awards)->firstWhere('key', 'sharpest_shooter');
    expect($shooter['type'])->toBe('team')
        ->and($shooter['label'])->toBe('Schärfste Schützen')
        ->and($shooter['subjects'])->toBe(['Team Shotgun']);
});

it('awards a two-team prize (Knapper Krimi) to both teams', function () {
    makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 5, 'home_throws' => 10, 'away_throws' => 10,
    ], $this->shotgun->id);

    $krimi = collect(app(StatisticsService::class)->awards($this->tournament))
        ->firstWhere('key', 'nail_biter');

    expect($krimi)->not->toBeNull()
        ->and($krimi['type'])->toBe('team')
        ->and($krimi['subjects'])->toHaveCount(2)
        ->and($krimi['subjects'])->toEqualCanonicalizing(['Team Shotgun', 'Team Bierherz']);
});

it('awards the Wurfkönig as a player-based prize', function () {
    $this->tournament->update(['determine_cup_king' => true]);

    $member = TeamMember::factory()->create(['team_id' => $this->shotgun->id, 'name' => 'Zoe Wurf']);
    $match = makeFinishedMatch($this->tournament, $this->shotgun, $this->bierherz, [
        'home_cups' => 6, 'away_cups' => 2, 'home_throws' => 8, 'away_throws' => 8,
    ], $this->shotgun->id);
    MatchMemberCup::factory()->create([
        'match_id' => $match->id,
        'team_member_id' => $member->id,
        'cups_hit' => 6,
    ]);

    $king = collect(app(StatisticsService::class)->awards($this->tournament))
        ->firstWhere('key', 'cup_king');

    expect($king)->not->toBeNull()
        ->and($king['type'])->toBe('player')
        ->and($king['label'])->toBe('Wurfkönig')
        ->and($king['subjects'])->toBe(['Zoe Wurf']);
});
