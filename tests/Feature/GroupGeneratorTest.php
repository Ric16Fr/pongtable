<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create();
    Table::factory()->count(2)->create(['tournament_id' => $this->tournament->id]);
    Team::factory()->count(8)->create(['tournament_id' => $this->tournament->id]);
});

it('distributes teams evenly across tables and creates round-robin matches', function () {
    app(GroupGeneratorService::class)->generate($this->tournament->fresh());

    $tournament = $this->tournament->fresh();

    expect($tournament->status)->toBe('group')
        ->and($tournament->groups()->count())->toBe(2);

    foreach ($tournament->groups as $group) {
        expect($group->teams()->count())->toBe(4)
            ->and($group->matches()->count())->toBe(6);
    }

    expect($tournament->matches()->count())->toBe(12);
});

it('refuses to generate when there are no tables', function () {
    $t = Tournament::factory()->create();
    Team::factory()->count(4)->create(['tournament_id' => $t->id]);

    expect(fn () => app(GroupGeneratorService::class)->generate($t))->toThrow(HttpException::class);
});

it('refuses to generate with fewer than 2 teams', function () {
    $t = Tournament::factory()->create();
    Table::factory()->create(['tournament_id' => $t->id]);
    Team::factory()->create(['tournament_id' => $t->id]);

    expect(fn () => app(GroupGeneratorService::class)->generate($t))->toThrow(HttpException::class);
});

it('produces a preview without persisting groups or matches', function () {
    $preview = app(GroupGeneratorService::class)->preview($this->tournament);

    expect($preview)->toHaveCount(2)
        ->and(collect($preview)->pluck('teams')->flatten())->toHaveCount(8)
        ->and($this->tournament->groups()->count())->toBe(0)
        ->and($this->tournament->matches()->count())->toBe(0);

});
