<?php

use App\Models\GameMatch;
use App\Models\MatchStat;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

function seedArchivedTournament(): Tournament
{
    $tournament = Tournament::factory()->create([
        'name' => 'Archiv Cup 2024',
        'status' => 'finished',
        'created_at' => now()->subYear(),
    ]);

    $table = Table::factory()->create(['tournament_id' => $tournament->id]);
    $champ = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Pokalheld']);
    $runnerUp = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Vize']);

    $final = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'ko',
        'ko_round' => 1,
        'ko_position' => 0,
        'table_id' => $table->id,
        'home_team_id' => $champ->id,
        'away_team_id' => $runnerUp->id,
        'winner_team_id' => $champ->id,
        'status' => 'finished',
    ]);
    MatchStat::create(['match_id' => $final->id, 'team_id' => $champ->id, 'cups_scored' => 10, 'throws' => 11, 'penalty_cups' => 0, 'duration_seconds' => 900]);
    MatchStat::create(['match_id' => $final->id, 'team_id' => $runnerUp->id, 'cups_scored' => 6, 'throws' => 13, 'penalty_cups' => 2, 'duration_seconds' => 900]);

    return $tournament;
}

it('lets an admin browse the archive and switch between Wertung and Statistik', function () {
    $this->actingAs(User::factory()->admin()->create());
    $past = seedArchivedTournament();
    Tournament::factory()->create(['name' => 'Aktueller Cup']);

    visit('/archiv')
        ->assertSee('Archiv')
        ->assertSee('Archiv Cup 2024')
        ->assertDontSee('Aktueller Cup')
        ->click('Archiv Cup 2024')
        ->assertSee('Archiv Cup 2024')
        ->assertSee('Wertung')
        ->click('@tab-statistik')
        ->assertSee('Turniersieger')
        ->assertSee('Team Pokalheld')
        ->assertNoJavascriptErrors();
});

it('forbids referees from reaching the archive', function () {
    $this->actingAs(User::factory()->referee()->create());

    $this->get('/archiv')->assertForbidden();
});
