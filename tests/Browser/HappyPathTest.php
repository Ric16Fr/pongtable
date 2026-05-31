<?php

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;

it('shows the dashboard with tournament data after authentication', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'admin']));
    Tournament::factory()->create(['name' => 'Happy Cup']);

    visit('/dashboard')
        ->assertSee('Happy Cup')
        ->assertSee('Dashboard')
        ->assertNoSmoke();
});

it('walks through a complete match from pre_entry to finished', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'admin']));

    $tournament = Tournament::factory()->create(['status' => 'group']);
    $table = Table::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Tisch 1']);
    $home = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Home']);
    $away = Team::factory()->create(['tournament_id' => $tournament->id, 'name' => 'Team Away']);

    $match = GameMatch::create([
        'tournament_id' => $tournament->id,
        'phase' => 'group',
        'table_id' => $table->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'pending',
    ]);

    $page = visit('/match/'.$match->id);

    $page->assertSee('Vor dem Spiel eintragen')
        ->assertSee('Team Home')
        ->assertSee('Team Away')
        ->press('🍺 Spiel starten');

    $page->assertSee('Verbleibende Zeit')
        ->press('⏹ Runde beenden');

    $page->assertSee('Getroffene Becher eintragen')
        ->fill('homeCups', '6')
        ->fill('awayCups', '4')
        ->press('✅ Ergebnis speichern');

    $page->assertSee('gewinnt')
        ->assertSee('6 : 4')
        ->assertNoJavascriptErrors();

    expect($match->fresh()->status)->toBe('finished')
        ->and($match->fresh()->winner_team_id)->toBe($home->id);
});

it('shows the public tournament page on a mobile viewport', function () {
    $tournament = Tournament::factory()->create(['name' => 'Mobile Cup']);

    visit('/t/'.$tournament->public_token)
        ->on()->mobile()
        ->assertSee('Mobile Cup')
        ->assertNoSmoke();
});
