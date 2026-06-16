<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

function browserMatch(Tournament $t, Table $table, ?Team $home, ?Team $away, string $status): GameMatch
{
    return GameMatch::create([
        'tournament_id' => $t->id,
        'table_id' => $table->id,
        'phase' => 'group',
        'home_team_id' => $home?->id,
        'away_team_id' => $away?->id,
        'status' => $status,
    ]);
}

it('shows the empty-state message when no matches exist', function () {
    $this->actingAs(User::factory()->referee()->create());
    Tournament::factory()->create();

    visit('/matches')
        ->assertSee('Keine Matches in dieser Ansicht')
        ->assertNoJavascriptErrors();
});

it('shows every match by default with the All filter active', function () {
    $this->actingAs(User::factory()->referee()->create());

    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Tisch 1']);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Pending']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Active']);
    $third = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Finished']);

    browserMatch($tournament, $table, $home, $away, 'pending');
    browserMatch($tournament, $table, $home, $third, 'active');
    browserMatch($tournament, $table, $away, $third, 'finished');

    visit('/matches')
        ->assertSee('Team Pending')
        ->assertSee('Team Active')
        ->assertSee('Team Finished')
        ->assertNoJavascriptErrors();
});

it('filters down to live matches when the Live button is pressed', function () {
    $this->actingAs(User::factory()->referee()->create());

    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $a = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team OnlyPending']);
    $b = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team OnlyActive']);

    browserMatch($tournament, $table, $a, $b, 'pending');
    browserMatch($tournament, $table, $a, $b, 'active');

    visit('/matches')
        ->press('Live')
        ->assertSee('Team OnlyActive')
        ->assertNoJavascriptErrors();
});

it('filters down to a single table when its button is pressed', function () {
    $this->actingAs(User::factory()->referee()->create());

    $tournament = Tournament::factory()->create();
    $tableOne = Table::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Tisch 1']);
    $tableTwo = Table::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Tisch 2']);
    $a = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Tisch1']);
    $b = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Tisch2']);
    $c = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Gegner']);

    browserMatch($tournament, $tableOne, $a, $c, 'pending');
    browserMatch($tournament, $tableTwo, $b, $c, 'pending');

    visit('/matches')
        ->press('Tisch 1')
        ->assertSee('Team Tisch1')
        ->assertDontSee('Team Tisch2')
        ->assertNoJavascriptErrors();
});

it('renders the "Sieger Vorrunde" placeholder for KO matches without teams yet', function () {
    $this->actingAs(User::factory()->referee()->create());

    $tournament = Tournament::factory()->create(['status' => 'ko']);
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);

    GameMatch::create([
        'tournament_id' => $tournament->id,
        'table_id' => $table->id,
        'phase' => 'ko',
        'ko_round' => 1,
        'ko_position' => 0,
        'home_team_id' => null,
        'away_team_id' => null,
        'status' => 'pending',
    ]);

    visit('/matches')
        ->assertSee('Sieger Vorrunde')
        ->assertSee('wartet')
        ->assertNoJavascriptErrors();
});

it('makes finished matches non-clickable but still visible', function () {
    $this->actingAs(User::factory()->referee()->create());

    $tournament = Tournament::factory()->create();
    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $a = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Done Home']);
    $b = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Done Away']);

    $match = GameMatch::create([
        'tournament_id' => $tournament->id,
        'table_id' => $table->id,
        'phase' => 'group',
        'home_team_id' => $a->id,
        'away_team_id' => $b->id,
        'winner_team_id' => $a->id,
        'status' => 'finished',
    ]);
    $match->stats()->create([
        'team_id' => $a->id, 'cups_scored' => 6, 'throws' => 8, 'penalty_cups' => 0,
    ]);
    $match->stats()->create([
        'team_id' => $b->id, 'cups_scored' => 4, 'throws' => 9, 'penalty_cups' => 1,
    ]);

    visit('/matches')
        ->assertSee('Done Home')
        ->assertSee('Done Away')
        ->assertSee('finished')
        ->assertNoJavascriptErrors();
});

it('redirects guests to the login when they hit the match list', function () {
    $this->get('/matches')->assertRedirect('/login');
});
