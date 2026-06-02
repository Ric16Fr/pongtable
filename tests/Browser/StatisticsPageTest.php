<?php

use App\Models\GameMatch;
use App\Models\MatchStat;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

function seedFinishedTournament(): Tournament
{
    $tournament = Tournament::factory()->create([
        'name' => 'Stats Cup',
        'status' => 'finished',
    ]);

    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $shotgun = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Shotgun']);
    $bierherz = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Bierherz']);

    // A finished group match providing stats.
    $group = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'group',
        'table_id' => $table->id,
        'home_team_id' => $shotgun->id,
        'away_team_id' => $bierherz->id,
        'winner_team_id' => $shotgun->id,
        'status' => 'finished',
    ]);
    MatchStat::create([
        'match_id' => $group->id, 'team_id' => $shotgun->id,
        'cups_scored' => 10, 'throws' => 12, 'penalty_cups' => 1, 'duration_seconds' => 600,
    ]);
    MatchStat::create([
        'match_id' => $group->id, 'team_id' => $bierherz->id,
        'cups_scored' => 4, 'throws' => 14, 'penalty_cups' => 3, 'duration_seconds' => 600,
    ]);

    // A finished KO final — required for champion banner.
    $final = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'ko',
        'ko_round' => 1,
        'ko_position' => 0,
        'table_id' => $table->id,
        'home_team_id' => $shotgun->id,
        'away_team_id' => $bierherz->id,
        'winner_team_id' => $shotgun->id,
        'status' => 'finished',
    ]);
    MatchStat::create([
        'match_id' => $final->id, 'team_id' => $shotgun->id,
        'cups_scored' => 10, 'throws' => 11, 'penalty_cups' => 0, 'duration_seconds' => 900,
    ]);
    MatchStat::create([
        'match_id' => $final->id, 'team_id' => $bierherz->id,
        'cups_scored' => 6, 'throws' => 13, 'penalty_cups' => 2, 'duration_seconds' => 900,
    ]);

    return $tournament;
}

it('shows the empty state on the statistics page when nothing is finished', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tournament::factory()->create(['name' => 'Empty Cup']);

    visit('/statistics')
        ->assertSee('Empty Cup')
        ->assertSee('Noch keine Daten')
        ->assertNoJavascriptErrors();
});

it('renders the champion banner after the final', function () {
    $this->actingAs(User::factory()->admin()->create());
    seedFinishedTournament();

    visit('/statistics')
        ->assertSee('Turniersieger')
        ->assertSee('Team Shotgun')
        ->assertNoJavascriptErrors();
});

it('renders all fun-stat tiles when applicable', function () {
    $this->actingAs(User::factory()->admin()->create());
    seedFinishedTournament();

    visit('/statistics')
        ->assertSee('Schärfste Schützen')
        ->assertSee('Wasserspeier')
        ->assertSee('Blitzsieg')
        ->assertSee('Marathonspieler')
        ->assertSee('Knapper Krimi')
        ->assertSee('Strafbechermagnet')
        ->assertSee('Effizienzrate')
        ->assertSee('Schluck-Olymp')
        ->assertNoJavascriptErrors();
});

it('shows the total cups counter', function () {
    $this->actingAs(User::factory()->admin()->create());
    seedFinishedTournament();

    visit('/statistics')
        ->assertSee('Cups gesamt');
});

it('forbids referees from reaching the statistics page', function () {
    $this->actingAs(User::factory()->referee()->create());

    $this->get('/statistics')->assertForbidden();
});
