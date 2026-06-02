<?php

use App\Models\GameMatch;
use App\Models\Group;
use App\Models\MatchStat;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;

it('renders the public page with phase label and tournament name', function () {
    $tournament = Tournament::factory()->create([
        'name' => 'Public View Cup',
        'status' => 'group',
    ]);

    visit('/t/'.$tournament->public_token)
        ->assertSee('Public View Cup')
        ->assertSee('Gruppenphase')
        ->assertNoJavascriptErrors();
});

it('shows the schiri-login button for guests on the public page', function () {
    $tournament = Tournament::factory()->create();

    visit('/t/'.$tournament->public_token)
        ->assertSee('Schiri-Login')
        ->assertNoJavascriptErrors();
});

it('renders the champion banner only after the tournament is finished', function () {
    $tournament = Tournament::factory()->create([
        'name' => 'Final Cup',
        'status' => 'finished',
    ]);
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $champ = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Champion Crew']);
    $runner = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Runner Up']);

    $final = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'ko',
        'ko_round' => 1,
        'ko_position' => 0,
        'table_id' => $table->id,
        'home_team_id' => $champ->id,
        'away_team_id' => $runner->id,
        'winner_team_id' => $champ->id,
        'status' => 'finished',
    ]);
    MatchStat::create([
        'match_id' => $final->id, 'team_id' => $champ->id,
        'cups_scored' => 10, 'throws' => 12, 'penalty_cups' => 0,
    ]);
    MatchStat::create([
        'match_id' => $final->id, 'team_id' => $runner->id,
        'cups_scored' => 6, 'throws' => 12, 'penalty_cups' => 1,
    ]);

    visit('/t/'.$tournament->public_token)
        ->assertSee('Turniersieger')
        ->assertSee('Champion Crew')
        ->assertNoJavascriptErrors();
});

it('hides the champion banner during the group phase', function () {
    $tournament = Tournament::factory()->create([
        'name' => 'Still Cup',
        'status' => 'group',
    ]);

    visit('/t/'.$tournament->public_token)
        ->assertDontSee('Turniersieger')
        ->assertNoJavascriptErrors();
});

it('renders the KO bracket section in the order: live, KO, groups', function () {
    $tournament = Tournament::factory()->create([
        'name' => 'Order Cup',
        'status' => 'ko',
    ]);
    $table = Table::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Hauptbühne']);

    // A group with two teams so the "Gruppenphase" section renders.
    $group = Group::create([
        'tournament_id' => $tournament->id,
        'table_id' => $table->id,
        'name' => 'Gruppe Order',
    ]);
    $alpha = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Order Alpha']);
    $beta = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Order Beta']);
    $group->teams()->attach([$alpha->id, $beta->id]);

    // A KO semi so the "KO-Bracket" section renders.
    GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'ko',
        'ko_round' => 2,
        'ko_position' => 0,
        'table_id' => $table->id,
        'home_team_id' => $alpha->id,
        'away_team_id' => $beta->id,
        'status' => 'pending',
    ]);

    // KO bracket appears before group standings (we just moved it up).
    // Browser pages don't have assertSeeInOrder; check the rendered HTML directly.
    $html = $this->get('/t/'.$tournament->public_token)->getContent();
    $koPosition = strpos($html, 'KO-Bracket');
    $groupPosition = strpos($html, 'Gruppenphase');

    expect($koPosition)->not->toBeFalse('"KO-Bracket" expected in markup')
        ->and($groupPosition)->not->toBeFalse('"Gruppenphase" expected in markup')
        ->and($koPosition)->toBeLessThan($groupPosition);

    visit('/t/'.$tournament->public_token)->assertNoJavascriptErrors();
});

it('renders the live face-off card when a match is in flight', function () {
    $tournament = Tournament::factory()->create(['status' => 'ko']);
    $table = Table::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Public-Tisch']);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Public Home']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Public Away']);

    GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'ko',
        'ko_round' => 2,
        'ko_position' => 0,
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'active',
    ]);

    visit('/t/'.$tournament->public_token)
        ->assertSee('Public Home')
        ->assertSee('Public Away')
        ->assertSee('Live')
        ->assertNoJavascriptErrors();
});

it('returns 404 for an unknown public token', function () {
    $this->get('/t/00000000-0000-0000-0000-000000000000')->assertNotFound();
});

it('renders the public page on a mobile viewport without JS errors', function () {
    $tournament = Tournament::factory()->create(['name' => 'Mobile Public Cup']);

    visit('/t/'.$tournament->public_token)
        ->on()->mobile()
        ->assertSee('Mobile Public Cup')
        ->assertNoJavascriptErrors();
});
