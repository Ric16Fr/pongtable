<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use App\Services\KoBracketService;
use App\Services\MatchResultService;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function finishAllMatchesOfPhase(Tournament $tournament, string $phase, ?callable $score = null): void
{
    $service = app(MatchResultService::class);
    foreach ($tournament->matches()->where('phase', $phase)->get() as $i => $match) {
        $service->startMatch($match, [
            'home_throws' => 5,
            'home_penalty_cups' => 0,
            'away_throws' => 5,
            'away_penalty_cups' => 0,
        ]);
        $service->endTimer($match->fresh());
        [$home, $away] = $score ? $score($match, $i) : [6, $i % 3];
        $service->saveResult($match->fresh(), $home, $away);
    }
}

function tournamentAfterGroupPhase(int $teamCount, bool $playPlacementMatches): Tournament
{
    $tournament = Tournament::factory()->create(['play_placement_matches' => $playPlacementMatches]);
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count($teamCount)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishAllMatchesOfPhase($tournament->fresh(), 'group');

    return $tournament->fresh();
}

it('starts the KO phase directly when placement matches are disabled', function () {
    $tournament = tournamentAfterGroupPhase(8, false);

    app(KoBracketService::class)->startKoPhase($tournament);

    $tournament = $tournament->fresh();

    expect($tournament->status)->toBe('ko')
        ->and($tournament->matches()->where('phase', 'placement')->count())->toBe(0);
});

it('starts a placement round for the non-qualified teams when enabled', function () {
    $tournament = tournamentAfterGroupPhase(8, true);

    $service = app(KoBracketService::class);
    $nonQualified = $service->nonQualifiedTeams($tournament);
    $service->startKoPhase($tournament);

    $tournament = $tournament->fresh();
    $placementMatches = $tournament->matches()->where('phase', 'placement')->get();

    // 8 teams in 2 groups, top 2 advance: 4 teams remain → 2 placement matches.
    expect($tournament->status)->toBe('placement')
        ->and($placementMatches)->toHaveCount(2)
        ->and($tournament->matches()->where('phase', 'ko')->count())->toBe(0);

    // Every non-qualified team plays exactly once.
    $participants = $placementMatches->flatMap(fn ($m) => [$m->home_team_id, $m->away_team_id]);
    expect($participants->sort()->values()->all())
        ->toBe($nonQualified->pluck('id')->sort()->values()->all());

    // Bottom pair (ko_position 0): last two of the overall standings, better-ranked at home.
    $bottomPair = $placementMatches->firstWhere('ko_position', 0);
    expect($bottomPair->home_team_id)->toBe($nonQualified[$nonQualified->count() - 2]->id)
        ->and($bottomPair->away_team_id)->toBe($nonQualified[$nonQualified->count() - 1]->id);
});

it('leaves the best non-qualified team without a match when their count is odd', function () {
    // 7 teams in 2 groups (4 + 3), top 2 advance: 3 teams remain → 1 match, best sits out.
    $tournament = tournamentAfterGroupPhase(7, true);

    $service = app(KoBracketService::class);
    $nonQualified = $service->nonQualifiedTeams($tournament);
    $service->startKoPhase($tournament);

    $placementMatches = $tournament->fresh()->matches()->where('phase', 'placement')->get();

    expect($nonQualified)->toHaveCount(3)
        ->and($placementMatches)->toHaveCount(1)
        ->and($placementMatches->first()->home_team_id)->toBe($nonQualified[1]->id)
        ->and($placementMatches->first()->away_team_id)->toBe($nonQualified[2]->id);
});

it('skips the placement round when fewer than two teams miss the KO phase', function () {
    // 4 teams in 2 groups: everyone qualifies → straight to KO despite the setting.
    $tournament = tournamentAfterGroupPhase(4, true);

    app(KoBracketService::class)->startKoPhase($tournament);

    $tournament = $tournament->fresh();

    expect($tournament->status)->toBe('ko')
        ->and($tournament->matches()->where('phase', 'placement')->count())->toBe(0);
});

it('refuses to start the KO phase while placement matches are unfinished', function () {
    $tournament = tournamentAfterGroupPhase(8, true);

    $service = app(KoBracketService::class);
    $service->startKoPhase($tournament);

    expect(fn () => $service->startKoPhase($tournament->fresh()))
        ->toThrow(HttpException::class);
});

it('generates the KO bracket after all placement matches are finished', function () {
    $tournament = tournamentAfterGroupPhase(8, true);

    $service = app(KoBracketService::class);
    $service->startKoPhase($tournament);

    finishAllMatchesOfPhase($tournament->fresh(), 'placement');

    expect($service->isPlacementRoundComplete($tournament->fresh()))->toBeTrue();

    $service->startKoPhase($tournament->fresh());

    $tournament = $tournament->fresh();

    expect($tournament->status)->toBe('ko')
        ->and($tournament->matches()->where('phase', 'ko')->count())->toBe(2);
});

it('placement matches do not alter group standings', function () {
    $tournament = tournamentAfterGroupPhase(8, true);

    $before = $tournament->groups->map(
        fn ($group) => $group->teams()->withPivot('points', 'wins', 'losses')->get()
            ->map(fn ($team) => [$team->id, $team->pivot->points, $team->pivot->wins, $team->pivot->losses])
    );

    $service = app(KoBracketService::class);
    $service->startKoPhase($tournament);
    finishAllMatchesOfPhase($tournament->fresh(), 'placement');

    $after = $tournament->fresh()->groups->map(
        fn ($group) => $group->teams()->withPivot('points', 'wins', 'losses')->get()
            ->map(fn ($team) => [$team->id, $team->pivot->points, $team->pivot->wins, $team->pivot->losses])
    );

    expect($after->toArray())->toBe($before->toArray());
});

it('reorders the leaderboard when a lower-ranked team wins its placement match', function () {
    $tournament = tournamentAfterGroupPhase(8, true);

    $service = app(KoBracketService::class);
    $nonQualified = $service->nonQualifiedTeams($tournament);
    $service->startKoPhase($tournament);

    // Away team (the worse-ranked of each pair) wins every placement match.
    finishAllMatchesOfPhase($tournament->fresh(), 'placement', fn () => [0, 6]);

    $rows = collect(Livewire::test('pages::leaderboard', ['tournamentId' => $tournament->id])
        ->instance()->rows());
    $rankedIds = $rows->pluck('team.id')->values();

    // The former last (away of the bottom pair) now sits above the former second-to-last.
    $formerSecondToLast = $nonQualified[$nonQualified->count() - 2]->id;
    $formerLast = $nonQualified[$nonQualified->count() - 1]->id;

    expect($rankedIds->search($formerLast))->toBeLessThan($rankedIds->search($formerSecondToLast));
});
