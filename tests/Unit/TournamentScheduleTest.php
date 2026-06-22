<?php

use App\Models\Tournament;

it('parses one entry per non-empty line and trims team names', function () {
    $tournament = new Tournament(['schedule' => " Team 1 ; Team 2 \nTeam 3;Team 4"]);

    expect($tournament->scheduleEntries())->toBe([
        ['Team 1', 'Team 2'],
        ['Team 3', 'Team 4'],
    ]);
});

it('skips blank lines and lines without any team', function () {
    $tournament = new Tournament(['schedule' => "A;B\n\n   \n ; \nSolo"]);

    expect($tournament->scheduleEntries())->toBe([
        ['A', 'B'],
        ['Solo'],
    ]);
});

it('returns an empty list for null or blank schedules', function () {
    expect(new Tournament(['schedule' => null])->scheduleEntries())->toBe([])
        ->and(new Tournament(['schedule' => "  \n ; "])->scheduleEntries())->toBe([]);
});

it('is only publicly visible when the option is on and a valid entry exists', function () {
    expect(new Tournament(['show_schedule' => false, 'schedule' => 'A;B'])->hasPublicSchedule())->toBeFalse()
        ->and(new Tournament(['show_schedule' => true, 'schedule' => ''])->hasPublicSchedule())->toBeFalse()
        ->and(new Tournament(['show_schedule' => true, 'schedule' => " ; \n "])->hasPublicSchedule())->toBeFalse()
        ->and(new Tournament(['show_schedule' => true, 'schedule' => 'A;B'])->hasPublicSchedule())->toBeTrue();
});
