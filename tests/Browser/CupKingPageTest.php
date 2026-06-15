<?php

use App\Models\GameMatch;
use App\Models\MatchMemberCup;
use App\Models\Table;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\User;

it('shows the setup hint when the rule is on but groups are not drawn yet', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create(['status' => 'setup', 'determine_cup_king' => true]);

    visit('/sonderregeln')
        ->assertSee('Wurfkönig ermitteln')
        ->assertSee('Die Gruppen müssen erst angelegt und zugelost')
        ->assertNoJavascriptErrors();
});

it('lets an admin name team members once the groups are drawn', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tournament = Tournament::factory()->create(['status' => 'group', 'determine_cup_king' => true]);
    $teamA = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Alpha']);
    $teamB = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Beta']);

    visit('/sonderregeln')
        ->assertSee('Teammitglieder benennen')
        ->click('@name-members-button')
        ->assertSee('Team Alpha')
        ->assertSee('Team Beta')
        ->fill("memberNames.{$teamA->id}.0", 'Paul')
        ->fill("memberNames.{$teamA->id}.1", 'Tom')
        ->fill("memberNames.{$teamB->id}.0", 'Heinz')
        ->fill("memberNames.{$teamB->id}.1", 'Karl')
        ->click('@save-members-button')
        ->assertSee('Teammitglieder gespeichert.')
        ->assertNoJavascriptErrors();

    expect($teamA->members()->pluck('name')->all())->toBe(['Paul', 'Tom']);
    expect($teamB->members()->pluck('name')->all())->toBe(['Heinz', 'Karl']);
});

it('opens the cup-distribution modal after saving a match result', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tournament = Tournament::factory()->create(['status' => 'group', 'determine_cup_king' => true]);
    $table = Table::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Tisch 1']);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Home']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Away']);
    $paul = TeamMember::factory()->create(['team_id' => $home->id, 'name' => 'Paul']);
    $tom = TeamMember::factory()->create(['team_id' => $home->id, 'name' => 'Tom']);
    $heinz = TeamMember::factory()->create(['team_id' => $away->id, 'name' => 'Heinz']);
    $karl = TeamMember::factory()->create(['team_id' => $away->id, 'name' => 'Karl']);

    $match = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'group',
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'pending',
    ]);

    $page = visit('/match/'.$match->id);

    $page->press('Spiel starten')
        ->press('Runde beenden')
        ->click('@confirm-end-round');

    $page->assertSee('Getroffene Becher eintragen')
        ->fill('homeCups', '10')
        ->fill('awayCups', '6')
        ->press('Ergebnis speichern');

    // Winner box AND the bonus modal both appear.
    $page->assertSee('Sieger')
        ->assertSee('Getroffene Becher verteilen')
        ->fill("cupDistribution.{$paul->id}", '6')
        ->fill("cupDistribution.{$tom->id}", '4')
        ->fill("cupDistribution.{$heinz->id}", '4')
        ->fill("cupDistribution.{$karl->id}", '2')
        ->click('@save-cup-distribution-button')
        ->assertSee('Becher verteilt.')
        ->assertNoJavascriptErrors();

    expect(MatchMemberCup::where('match_id', $match->id)->count())->toBe(4);
    expect(MatchMemberCup::where('team_member_id', $paul->id)->value('cups_hit'))->toBe(6);
});

it('shows the Wurfkönig tile on the statistics page when the rule is on', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tournament = Tournament::factory()->create(['status' => 'finished', 'determine_cup_king' => true]);
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Home']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Away']);
    $paul = TeamMember::factory()->create(['team_id' => $home->id, 'name' => 'Paul']);

    $match = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'group',
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'winner_team_id' => $home->id,
        'status' => 'finished',
    ]);
    MatchMemberCup::create(['match_id' => $match->id, 'team_member_id' => $paul->id, 'cups_hit' => 8]);

    visit('/statistics')
        ->assertSee('Wurfkönig')
        ->assertSee('Paul')
        ->assertNoJavascriptErrors();
});
