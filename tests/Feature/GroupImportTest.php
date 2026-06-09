<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create();
});

it('imports a multi-line CSV with header row, distributing teams round-robin across groups', function () {
    Table::factory()->count(4)->create(['tournament_id' => $this->tournament->id]);

    $csv = <<<'CSV'
Gruppe A;Gruppe B;Gruppe C;Gruppe D
Renx & Philipp;Stefan & Henry;Kitty & Till;Schachi & Huschke
Dennis & Yves H.;Paul N. & Niklas;Tobi & Richard;Felo & Kluwe
Kevin & Grodon;Valle & TB;Justin & Yves M.;Franik & MB
Felix & Lude;Mörre & Gussi;John & Ede;Marvin & Luki
CSV;

    app(GroupGeneratorService::class)->importFromCsv($this->tournament, $csv);

    $tournament = $this->tournament->fresh();
    expect($tournament->status)->toBe('group')
        ->and($tournament->groups()->count())->toBe(4)
        ->and($tournament->teams()->count())->toBe(16);

    $groupA = $tournament->groups()->where('name', 'Gruppe A')->with('teams')->first();
    expect($groupA->teams->pluck('name')->all())->toBe([
        'Renx & Philipp', 'Dennis & Yves H.', 'Kevin & Grodon', 'Felix & Lude',
    ]);

    // 4 teams per group → 6 round-robin matches per group → 24 total.
    expect($tournament->matches()->count())->toBe(24);
});

it('imports a flat single-line CSV using the "Gruppe " prefix as header marker', function () {
    Table::factory()->count(4)->create(['tournament_id' => $this->tournament->id]);

    $csv = 'Gruppe A;Gruppe B;Gruppe C;Gruppe D;Renx & Philipp;Stefan & Henry;Kitty & Till;Schachi & Huschke;Dennis & Yves H.;Paul N. & Niklas;Tobi & Richard;Felo & Kluwe;Kevin & Grodon;Valle & TB;Justin & Yves M.;Franik & MB;Felix & Lude;Mörre & Gussi;John & Ede;Marvin & Luki;';

    app(GroupGeneratorService::class)->importFromCsv($this->tournament, $csv);

    $tournament = $this->tournament->fresh();
    expect($tournament->status)->toBe('group')
        ->and($tournament->groups()->count())->toBe(4)
        ->and($tournament->teams()->count())->toBe(16);

    $groupB = $tournament->groups()->where('name', 'Gruppe B')->with('teams')->first();
    expect($groupB->teams->pluck('name')->all())->toBe([
        'Stefan & Henry', 'Paul N. & Niklas', 'Valle & TB', 'Mörre & Gussi',
    ]);
});

it('replaces any existing teams when importing from CSV', function () {
    Table::factory()->count(2)->create(['tournament_id' => $this->tournament->id]);
    Team::factory()->count(5)->create(['tournament_id' => $this->tournament->id, 'name' => 'Alter Name']);

    $csv = "Gruppe A;Gruppe B\nNeu 1;Neu 2\nNeu 3;Neu 4";

    app(GroupGeneratorService::class)->importFromCsv($this->tournament, $csv);

    $tournament = $this->tournament->fresh();
    expect($tournament->teams()->count())->toBe(4)
        ->and($tournament->teams()->where('name', 'Alter Name')->exists())->toBeFalse();
});

it('refuses to import when the table count does not match the group count', function () {
    Table::factory()->count(3)->create(['tournament_id' => $this->tournament->id]);

    $csv = "Gruppe A;Gruppe B\nT1;T2\nT3;T4";

    expect(fn () => app(GroupGeneratorService::class)->importFromCsv($this->tournament, $csv))
        ->toThrow(HttpException::class);

    expect($this->tournament->fresh()->groups()->count())->toBe(0);
});

it('refuses to import a tournament that has already left setup', function () {
    Table::factory()->count(2)->create(['tournament_id' => $this->tournament->id]);
    $this->tournament->update(['status' => 'group']);

    $csv = "Gruppe A;Gruppe B\nT1;T2\nT3;T4";

    expect(fn () => app(GroupGeneratorService::class)->importFromCsv($this->tournament, $csv))
        ->toThrow(HttpException::class);
});

it('refuses to import a CSV with fewer than 2 teams per group', function () {
    Table::factory()->count(2)->create(['tournament_id' => $this->tournament->id]);

    $csv = "Gruppe A;Gruppe B\nT1;T2";

    expect(fn () => app(GroupGeneratorService::class)->importFromCsv($this->tournament, $csv))
        ->toThrow(HttpException::class);
});

it('refuses to import an empty CSV', function () {
    Table::factory()->count(2)->create(['tournament_id' => $this->tournament->id]);

    expect(fn () => app(GroupGeneratorService::class)->importFromCsv($this->tournament, ''))
        ->toThrow(HttpException::class);
});
