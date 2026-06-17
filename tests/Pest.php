<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use App\Services\KoBracketService;
use App\Services\MatchResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Finish a single match with an explicit score (home cups : away cups).
 */
function finishMatch(GameMatch $match, int $homeCups, int $awayCups): void
{
    $service = app(MatchResultService::class);
    $service->startMatch($match, [
        'home_throws' => 5,
        'home_penalty_cups' => 0,
        'away_throws' => 5,
        'away_penalty_cups' => 0,
    ]);
    $service->endTimer($match->fresh());
    $service->saveResult($match->fresh(), $homeCups, $awayCups);
}

function finishGroupPhase(Tournament $tournament): void
{
    foreach ($tournament->matches()->where('phase', 'group')->get() as $i => $match) {
        // Varied scores so there are no draws and cup diffs differ between teams.
        finishMatch($match, 6, $i % 3);
    }
}

/**
 * Finish every ready KO match repeatedly. Each round's winners are advanced
 * into a freshly created next round, so we loop until the final flips the
 * tournament to "finished". Home always wins.
 */
function finishKoBracket(Tournament $tournament): void
{
    while ($tournament->fresh()->status === 'ko') {
        $ready = $tournament->fresh()->matches()
            ->where('phase', 'ko')
            ->where('status', '!=', 'finished')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->get();

        if ($ready->isEmpty()) {
            break;
        }

        foreach ($ready as $match) {
            finishMatch($match->fresh(), 6, 1);
        }
    }
}

/**
 * Build a fully played-out, finished tournament: groups → KO bracket.
 */
function buildFinishedTournament(int $teamCount, int $tableCount = 2): Tournament
{
    $tournament = Tournament::factory()->create();
    Table::factory()->count($tableCount)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count($teamCount)->create(['tournament_id' => $tournament->id]);

    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishGroupPhase($tournament->fresh());
    app(KoBracketService::class)->startKoPhase($tournament->fresh());
    finishKoBracket($tournament->fresh());

    return $tournament->fresh();
}
