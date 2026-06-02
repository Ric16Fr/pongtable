<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use App\Services\KoBracketService;
use App\Services\MatchResultService;
use Symfony\Component\HttpKernel\Exception\HttpException;

function finishAllGroupMatchesCleanly(Tournament $tournament): void
{
    $svc = app(MatchResultService::class);
    foreach ($tournament->matches()->where('phase', 'group')->get() as $i => $m) {
        $svc->startMatch($m, [
            'home_throws' => 5, 'home_penalty_cups' => 0,
            'away_throws' => 5, 'away_penalty_cups' => 0,
        ]);
        $svc->endTimer($m->fresh());
        $svc->saveResult($m->fresh(), 6, max(0, $i % 3));
    }
}

it('builds an 8-team KO bracket out of a 4-group qualifier', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(8)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishAllGroupMatchesCleanly($tournament->fresh());

    app(KoBracketService::class)->startKoPhase($tournament->fresh());

    $tournament = $tournament->fresh();
    expect($tournament->status)->toBe('ko');

    // 4 group winners + 4 runners-up = 8 → quarterfinals (ko_round = 4)
    $quarters = $tournament->matches()->where('phase', 'ko')->get();
    expect($quarters->count())->toBe(4);
    expect($quarters->pluck('ko_round')->unique()->all())->toBe([4]);
});

it('cross-pairs winners with runners-up from the opposite bracket end', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(8)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishAllGroupMatchesCleanly($tournament->fresh());

    app(KoBracketService::class)->startKoPhase($tournament->fresh());

    // The first KO match should pair the 1st-place group winner of one table
    // with the 2nd-place team of the *last* table — i.e., not the same group.
    $first = $tournament->fresh()->matches()
        ->where('phase', 'ko')
        ->orderBy('ko_position')
        ->first();

    expect($first->home_team_id)->not->toBe($first->away_team_id);

    $homeGroup = $tournament->groups()->whereHas('teams', fn ($q) => $q->where('teams.id', $first->home_team_id))->first();
    $awayGroup = $tournament->groups()->whereHas('teams', fn ($q) => $q->where('teams.id', $first->away_team_id))->first();

    expect($homeGroup->id)->not->toBe($awayGroup->id);
});

it('places winners into home- or away-slots based on ko_position parity', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishAllGroupMatchesCleanly($tournament->fresh());

    app(KoBracketService::class)->startKoPhase($tournament->fresh());

    // Two semifinal matches at ko_round = 1 (since 2 teams = 1 round)... actually
    // 2 winners + 2 runners-up = 4 → semis at ko_round = 2.
    $semis = $tournament->fresh()
        ->matches()
        ->where('phase', 'ko')
        ->where('ko_round', 2)
        ->orderBy('ko_position')
        ->get();

    expect($semis)->toHaveCount(2);

    $svc = app(MatchResultService::class);

    // Finish semi 0 (even position → next round home_team_id).
    $semi0 = $semis[0];
    $svc->startMatch($semi0, [
        'home_throws' => 5, 'home_penalty_cups' => 0,
        'away_throws' => 5, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($semi0->fresh());
    $svc->saveResult($semi0->fresh(), 6, 2);
    $semi0Winner = $semi0->fresh()->winner_team_id;

    $final = $tournament->fresh()->matches()->where('ko_round', 1)->first();
    expect($final->home_team_id)->toBe($semi0Winner)
        ->and($final->away_team_id)->toBeNull();

    // Finish semi 1 (odd position → next round away_team_id).
    $semi1 = $semis[1];
    $svc->startMatch($semi1, [
        'home_throws' => 5, 'home_penalty_cups' => 0,
        'away_throws' => 5, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($semi1->fresh());
    $svc->saveResult($semi1->fresh(), 6, 1);
    $semi1Winner = $semi1->fresh()->winner_team_id;

    $final = $final->fresh();
    expect($final->home_team_id)->toBe($semi0Winner)
        ->and($final->away_team_id)->toBe($semi1Winner);
});

it('reuses the existing next-round match instead of creating duplicates', function () {
    // The advance-winner logic uses firstOrCreate. Two semifinals feeding the
    // same final must result in EXACTLY one final, not two.
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishAllGroupMatchesCleanly($tournament->fresh());

    app(KoBracketService::class)->startKoPhase($tournament->fresh());

    $svc = app(MatchResultService::class);
    foreach ($tournament->fresh()->matches()->where('phase', 'ko')->where('ko_round', 2)->get() as $semi) {
        $svc->startMatch($semi, [
            'home_throws' => 5, 'home_penalty_cups' => 0,
            'away_throws' => 5, 'away_penalty_cups' => 0,
        ]);
        $svc->endTimer($semi->fresh());
        $svc->saveResult($semi->fresh(), 6, 2);
    }

    expect($tournament->fresh()->matches()->where('ko_round', 1)->count())->toBe(1);
});

it('marks the tournament finished only after the final is saved', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());
    finishAllGroupMatchesCleanly($tournament->fresh());

    app(KoBracketService::class)->startKoPhase($tournament->fresh());

    $svc = app(MatchResultService::class);
    foreach ($tournament->fresh()->matches()->where('ko_round', 2)->get() as $semi) {
        $svc->startMatch($semi, [
            'home_throws' => 5, 'home_penalty_cups' => 0,
            'away_throws' => 5, 'away_penalty_cups' => 0,
        ]);
        $svc->endTimer($semi->fresh());
        $svc->saveResult($semi->fresh(), 6, 1);
    }

    // After semis: still "ko"
    expect($tournament->fresh()->status)->toBe('ko');

    $final = $tournament->fresh()->matches()->where('ko_round', 1)->first();
    $svc->startMatch($final, [
        'home_throws' => 5, 'home_penalty_cups' => 0,
        'away_throws' => 5, 'away_penalty_cups' => 0,
    ]);
    $svc->endTimer($final->fresh());
    $svc->saveResult($final->fresh(), 6, 2);

    expect($tournament->fresh()->status)->toBe('finished');
});

it('refuses to start the KO phase while group matches are still open', function () {
    $tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $tournament->id]);
    Team::factory()->count(4)->create(['tournament_id' => $tournament->id]);
    app(GroupGeneratorService::class)->generate($tournament->fresh());

    // Don't finish any matches.
    expect(fn () => app(KoBracketService::class)->startKoPhase($tournament->fresh()))
        ->toThrow(HttpException::class);

    expect($tournament->fresh()->status)->toBe('group');
});

it('does nothing when startKoPhase is called outside group status', function () {
    $tournament = Tournament::factory()->create(['status' => 'setup']);

    app(KoBracketService::class)->startKoPhase($tournament);
    expect($tournament->fresh()->status)->toBe('setup');

    $finished = Tournament::factory()->create(['status' => 'finished']);
    app(KoBracketService::class)->startKoPhase($finished);
    expect($finished->fresh()->status)->toBe('finished');
});

it('does not advance a non-finished KO match', function () {
    $tournament = Tournament::factory()->create(['status' => 'ko']);
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $home = Team::factory()->create(['tournament_id' => $tournament->id]);
    $away = Team::factory()->create(['tournament_id' => $tournament->id]);

    $match = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'ko',
        'ko_round' => 2,
        'ko_position' => 0,
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'pending',
    ]);

    app(KoBracketService::class)->advanceKoWinner($match);

    expect($tournament->fresh()->matches()->where('ko_round', 1)->count())->toBe(0);
});
