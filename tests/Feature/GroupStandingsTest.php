<?php

use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\KoBracketService;

/**
 * Build a finished match between two teams with the given cup score.
 * The winner is implicit from the higher score; ties pick "home" by default.
 */
function recordGroupMatch(Tournament $tournament, Group $group, Team $home, Team $away, int $homeCups, int $awayCups): void
{
    $winnerId = $homeCups >= $awayCups ? $home->id : $away->id;

    $match = GameMatch::create([
        'tournament_id' => $tournament->id,
        'group_id' => $group->id,
        'phase' => 'group',
        'table_id' => $group->table_id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'winner_team_id' => $winnerId,
        'status' => 'finished',
    ]);

    $match->stats()->create(['team_id' => $home->id, 'cups_scored' => $homeCups, 'throws' => 0, 'penalty_cups' => 0]);
    $match->stats()->create(['team_id' => $away->id, 'cups_scored' => $awayCups, 'throws' => 0, 'penalty_cups' => 0]);
}

/**
 * Set the pivot row on group_team for a team (points, cups totals).
 */
function setGroupPivot(Group $group, Team $team, int $points, int $cupsScored, int $cupsConceded, int $wins = 0, int $losses = 0): void
{
    $group->teams()->updateExistingPivot($team->id, [
        'points' => $points,
        'wins' => $wins,
        'losses' => $losses,
        'cups_scored_total' => $cupsScored,
        'cups_conceded_total' => $cupsConceded,
    ]);
}

beforeEach(function () {
    $this->tournament = Tournament::factory()->create();
    $this->table = Table::factory()->create(['tournament_id' => $this->tournament->id]);
    $this->group = Group::create([
        'tournament_id' => $this->tournament->id,
        'table_id' => $this->table->id,
        'name' => 'Gruppe A',
    ]);
});

it('orders teams by overall points first', function () {
    $a = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Alpha']);
    $b = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Bravo']);
    $c = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Charlie']);
    $this->group->teams()->attach([$a->id, $b->id, $c->id]);

    setGroupPivot($this->group, $a, points: 3, cupsScored: 10, cupsConceded: 8);
    setGroupPivot($this->group, $b, points: 9, cupsScored: 20, cupsConceded: 5);
    setGroupPivot($this->group, $c, points: 6, cupsScored: 16, cupsConceded: 12);

    $names = app(KoBracketService::class)
        ->groupStandings($this->group->fresh())
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Bravo', 'Charlie', 'Alpha']);
});

it('uses direkter Vergleich to break a two-team tie on points', function () {
    // Alpha has worse cup diff than Bravo, but Alpha won the head-to-head match.
    // Per official rules, "direkter Vergleich" outranks Torverhältnis.
    $a = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Alpha']);
    $b = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Bravo']);
    $c = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Charlie']);
    $this->group->teams()->attach([$a->id, $b->id, $c->id]);

    // Head-to-head: Alpha beat Bravo 10:8.
    recordGroupMatch($this->tournament, $this->group, $a, $b, 10, 8);

    // Pivot totals (across all matches in real life, but stubbed here):
    // Alpha: 3 points, cup diff +2  (10 - 8)
    // Bravo: 3 points, cup diff +5  (20 - 15, larger diff but lost h2h)
    // Charlie: 0 points, cup diff −7
    setGroupPivot($this->group, $a, points: 3, cupsScored: 10, cupsConceded: 8);
    setGroupPivot($this->group, $b, points: 3, cupsScored: 20, cupsConceded: 15);
    setGroupPivot($this->group, $c, points: 0, cupsScored: 5, cupsConceded: 12);

    $names = app(KoBracketService::class)
        ->groupStandings($this->group->fresh())
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('uses a head-to-head mini-table for a three-team tie on points', function () {
    // Three teams all tied on 3 overall points.
    // Internal cycle within them: Alpha > Bravo, Bravo > Charlie, Charlie > Alpha.
    // Each team has 3 h2h points, so the cycle is unresolved → falls through
    // to Torverhältnis (overall cup diff).
    $a = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Alpha']);
    $b = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Bravo']);
    $c = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Charlie']);
    $this->group->teams()->attach([$a->id, $b->id, $c->id]);

    recordGroupMatch($this->tournament, $this->group, $a, $b, 10, 7);
    recordGroupMatch($this->tournament, $this->group, $b, $c, 10, 6);
    recordGroupMatch($this->tournament, $this->group, $c, $a, 10, 8);

    // Overall: each team has 3 points. Cup diff: A +3-2=+1, B +3-4=−1, C +4-2=+2.
    setGroupPivot($this->group, $a, points: 3, cupsScored: 18, cupsConceded: 17);
    setGroupPivot($this->group, $b, points: 3, cupsScored: 17, cupsConceded: 16);
    setGroupPivot($this->group, $c, points: 3, cupsScored: 16, cupsConceded: 18);

    $names = app(KoBracketService::class)
        ->groupStandings($this->group->fresh())
        ->pluck('name')
        ->all();

    // All 3 teams 3 h2h points → fall back to overall cup diff.
    // Diffs: Alpha +1, Bravo +1, Charlie −2 → Charlie last; Alpha vs Bravo:
    //   same diff, fall back to cups scored: Alpha 18 > Bravo 17.
    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('three-team tie resolves cleanly when one team won both head-to-heads', function () {
    $a = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Alpha']);
    $b = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Bravo']);
    $c = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Charlie']);
    $this->group->teams()->attach([$a->id, $b->id, $c->id]);

    // Alpha beats both Bravo and Charlie; Bravo also beats Charlie.
    recordGroupMatch($this->tournament, $this->group, $a, $b, 10, 8);
    recordGroupMatch($this->tournament, $this->group, $a, $c, 10, 7);
    recordGroupMatch($this->tournament, $this->group, $b, $c, 10, 6);

    // Pretend all three are tied at 6 overall points (set externally below).
    // h2h points: Alpha 6, Bravo 3, Charlie 0 → unambiguous order.
    setGroupPivot($this->group, $a, points: 6, cupsScored: 20, cupsConceded: 15);
    setGroupPivot($this->group, $b, points: 6, cupsScored: 18, cupsConceded: 16);
    setGroupPivot($this->group, $c, points: 6, cupsScored: 13, cupsConceded: 20);

    $names = app(KoBracketService::class)
        ->groupStandings($this->group->fresh())
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('falls back to cups scored when points, h2h, and cup diff are all tied', function () {
    // Two teams, identical points and (zero) head-to-head, identical cup diff,
    // different cups scored.
    $a = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Alpha']);
    $b = Team::factory()->create(['tournament_id' => $this->tournament->id, 'name' => 'Bravo']);
    $this->group->teams()->attach([$a->id, $b->id]);

    // No match between them so h2h is 0 vs 0.
    setGroupPivot($this->group, $a, points: 3, cupsScored: 18, cupsConceded: 18);
    setGroupPivot($this->group, $b, points: 3, cupsScored: 12, cupsConceded: 12);

    $names = app(KoBracketService::class)
        ->groupStandings($this->group->fresh())
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpha', 'Bravo']);
});
