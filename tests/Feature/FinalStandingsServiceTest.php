<?php

use App\Models\GameMatch;
use App\Models\Team;
use App\Services\FinalStandingsService;

function cupDiff(Team $team): int
{
    $pivot = $team->groups()->first()->pivot;

    return $pivot->cups_scored_total - $pivot->cups_conceded_total;
}

it('ranks the champion first and the runner-up second', function () {
    $tournament = buildFinishedTournament(4);
    expect($tournament->status)->toBe('finished');

    $final = $tournament->matches()->where('ko_round', 1)->first();
    $championId = $final->winner_team_id;
    $runnerUpId = $final->home_team_id === $championId ? $final->away_team_id : $final->home_team_id;

    $standings = app(FinalStandingsService::class)->standings($tournament);

    expect($standings[0]['rank'])->toBe(1)
        ->and($standings[0]['team']->id)->toBe($championId)
        ->and($standings[1]['rank'])->toBe(2)
        ->and($standings[1]['team']->id)->toBe($runnerUpId);
});

it('ranks the two semifinal losers third and fourth by cup difference', function () {
    $tournament = buildFinishedTournament(4);

    $semiLoserIds = $tournament->matches()
        ->where('ko_round', 2)
        ->get()
        ->map(fn (GameMatch $m) => $m->winner_team_id === $m->home_team_id ? $m->away_team_id : $m->home_team_id)
        ->all();

    $standings = app(FinalStandingsService::class)->standings($tournament);

    $thirdPlace = $standings[2]['team'];
    $fourthPlace = $standings[3]['team'];

    // Both teams ranked 3rd/4th are exactly the semifinal losers.
    expect([$thirdPlace->id, $fourthPlace->id])->toEqualCanonicalizing($semiLoserIds)
        ->and(cupDiff($thirdPlace))->toBeGreaterThanOrEqual(cupDiff($fourthPlace));

    // The better cup difference takes the higher (3rd) place.
});

it('assigns every team a unique, gapless rank', function () {
    $tournament = buildFinishedTournament(6);

    $standings = app(FinalStandingsService::class)->standings($tournament);

    expect($standings)->toHaveCount(6)
        ->and($standings->pluck('rank')->all())->toBe([1, 2, 3, 4, 5, 6])
        ->and($standings->pluck('team.id')->unique()->count())->toBe(6);
});

it('places teams that never reached the KO bracket last', function () {
    $tournament = buildFinishedTournament(6);

    $koTeamIds = $tournament->matches()
        ->where('phase', 'ko')
        ->get()
        ->flatMap(fn (GameMatch $m) => [$m->home_team_id, $m->away_team_id])
        ->filter()
        ->unique()
        ->all();

    $standings = app(FinalStandingsService::class)->standings($tournament);

    // The four KO teams occupy ranks 1-4, the two non-KO teams ranks 5-6.
    $topFourIds = $standings->take(4)->pluck('team.id')->all();
    $bottomTwoIds = $standings->slice(4)->pluck('team.id')->all();

    expect($topFourIds)->toEqualCanonicalizing($koTeamIds)
        ->and(array_intersect($bottomTwoIds, $koTeamIds))->toBeEmpty();
});
