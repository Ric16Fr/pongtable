<?php

use App\Models\GameMatch;
use App\Models\MatchMemberCup;
use App\Models\Table;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\User;
use App\Services\StatisticsService;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('defaults the "Wurfkönig ermitteln" switch to off', function () {
    Tournament::factory()->create();

    Livewire::test('pages::special-rules')
        ->assertSet('determineCupKing', false);
});

it('persists the determine_cup_king toggle to the tournament', function () {
    $tournament = Tournament::factory()->create();

    Livewire::test('pages::special-rules')
        ->set('determineCupKing', true)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($tournament->fresh()->determine_cup_king)->toBeTrue();

    Livewire::test('pages::special-rules')
        ->set('determineCupKing', false)
        ->call('saveSettings');

    expect($tournament->fresh()->determine_cup_king)->toBeFalse();
});

it('saves the named team members, trimming blanks', function () {
    $tournament = Tournament::factory()->create(['status' => 'group', 'determine_cup_king' => true]);
    $teamA = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team A']);
    $teamB = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team B']);

    Livewire::test('pages::special-rules')
        ->set("memberNames.$teamA->id", ['Paul', 'Tom'])
        ->set("memberNames.$teamB->id", ['Heinz', '   '])
        ->call('saveMembers')
        ->assertHasNoErrors();

    expect($teamA->members()->pluck('name')->all())->toBe(['Paul', 'Tom'])
        ->and($teamB->members()->pluck('name')->all())->toBe(['Heinz']);
});

it('replaces existing members when saving again', function () {
    $tournament = Tournament::factory()->create(['status' => 'group', 'determine_cup_king' => true]);
    $team = Team::factory()->create(['tournament_id' => $tournament->id]);
    TeamMember::factory()->create(['team_id' => $team->id, 'name' => 'Alt']);

    Livewire::test('pages::special-rules')
        ->set("memberNames.$team->id", ['Neu1', 'Neu2'])
        ->call('saveMembers');

    expect($team->members()->pluck('name')->all())->toBe(['Neu1', 'Neu2']);
});

it('stores and replaces the cup distribution for a match', function () {
    [$tournament, $match, $members] = makeCupKingMatch();

    Livewire::test('pages::match-score', ['match' => $match])
        ->set('cupDistribution', [
            $members['paul']->id => 5,
            $members['tom']->id => 3,
            $members['heinz']->id => 2,
            $members['karl']->id => 0,
        ])
        ->call('saveCupDistribution')
        ->assertHasNoErrors();

    expect(MatchMemberCup::where('match_id', $match->id)->count())->toBe(4)
        ->and(MatchMemberCup::where('team_member_id', $members['paul']->id)->value('cups_hit'))->toBe(5);

    // Saving again replaces the prior distribution rather than duplicating it.
    Livewire::test('pages::match-score', ['match' => $match])
        ->set('cupDistribution', [$members['paul']->id => 1, $members['tom']->id => 1])
        ->call('saveCupDistribution');

    expect(MatchMemberCup::where('match_id', $match->id)->count())->toBe(2);
    expect(MatchMemberCup::where('team_member_id', $members['paul']->id)->value('cups_hit'))->toBe(1);
});

it('ignores member ids that do not belong to the match teams', function () {
    [$tournament, $match, $members] = makeCupKingMatch();
    $foreign = TeamMember::factory()->create();

    Livewire::test('pages::match-score', ['match' => $match])
        ->set('cupDistribution', [$members['paul']->id => 4, $foreign->id => 9])
        ->call('saveCupDistribution');

    expect(MatchMemberCup::where('match_id', $match->id)->count())->toBe(1)
        ->and(MatchMemberCup::where('team_member_id', $foreign->id)->exists())->toBeFalse();
});

it('crowns the player with the most cups across the tournament as Wurfkönig', function () {
    [$tournament, $match, $members] = makeCupKingMatch();

    // Second match so cups accumulate across games.
    $match2 = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'group',
        'table_id' => $match->table_id,
        'home_team_id' => $match->home_team_id,
        'away_team_id' => $match->away_team_id,
        'status' => 'finished',
    ]);

    MatchMemberCup::create(['match_id' => $match->id, 'team_member_id' => $members['paul']->id, 'cups_hit' => 4]);
    MatchMemberCup::create(['match_id' => $match->id, 'team_member_id' => $members['tom']->id, 'cups_hit' => 6]);
    MatchMemberCup::create(['match_id' => $match2->id, 'team_member_id' => $members['paul']->id, 'cups_hit' => 5]);
    MatchMemberCup::create(['match_id' => $match2->id, 'team_member_id' => $members['tom']->id, 'cups_hit' => 1]);

    // Paul: 9, Tom: 7 → Paul is Wurfkönig.
    $cupKing = app(StatisticsService::class)->summary($tournament->fresh())['cup_king'];

    expect($cupKing['name'])->toBe('Paul')
        ->and($cupKing['cups'])->toBe(9)
        ->and($cupKing['team'])->toBe('Team A');
});

it('hides the Wurfkönig stat when the rule is off', function () {
    [$tournament, $match, $members] = makeCupKingMatch();
    $tournament->update(['determine_cup_king' => false]);
    MatchMemberCup::create(['match_id' => $match->id, 'team_member_id' => $members['paul']->id, 'cups_hit' => 4]);

    expect(app(StatisticsService::class)->summary($tournament->fresh())['cup_king'])->toBeNull();
});

it('returns no Wurfkönig when no cups have been distributed', function () {
    [$tournament] = makeCupKingMatch();

    expect(app(StatisticsService::class)->summary($tournament->fresh())['cup_king'])->toBeNull();
});

/**
 * Build a finished cup-king tournament: Team A (Paul, Tom) vs Team B (Heinz, Karl).
 *
 * @return array{0: Tournament, 1: GameMatch, 2: array<string, TeamMember>}
 */
function makeCupKingMatch(): array
{
    $tournament = Tournament::factory()->create(['status' => 'ko', 'determine_cup_king' => true]);
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $teamA = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team A']);
    $teamB = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team B']);

    $members = [
        'paul' => TeamMember::factory()->create(['team_id' => $teamA->id, 'name' => 'Paul']),
        'tom' => TeamMember::factory()->create(['team_id' => $teamA->id, 'name' => 'Tom']),
        'heinz' => TeamMember::factory()->create(['team_id' => $teamB->id, 'name' => 'Heinz']),
        'karl' => TeamMember::factory()->create(['team_id' => $teamB->id, 'name' => 'Karl']),
    ];

    $match = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'group',
        'table_id' => $table->id,
        'home_team_id' => $teamA->id,
        'away_team_id' => $teamB->id,
        'status' => 'scoring',
    ]);

    return [$tournament, $match, $members];
}
