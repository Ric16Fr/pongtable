<?php

use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('lists past tournaments but not the current one', function () {
    $past = Tournament::factory()->create(['name' => 'Cup 2024', 'created_at' => now()->subYear()]);
    $current = Tournament::factory()->create(['name' => 'Cup 2025']);

    Livewire::test('pages::archive')
        ->assertSee('Cup 2024')
        ->assertDontSee('Cup 2025');

    expect(Tournament::query()->latest()->value('id'))->toBe($current->id);
});

it('shows an empty state when there is only the current tournament', function () {
    Tournament::factory()->create(['name' => 'Einziger Cup']);

    Livewire::test('pages::archive')
        ->assertDontSee('Einziger Cup')
        ->assertSee('Noch keine vergangenen Turniere');
});

it('renders a past tournament with switchable Wertung and Statistik tabs', function () {
    $past = Tournament::factory()->create(['name' => 'Cup 2024', 'status' => 'finished', 'created_at' => now()->subYear()]);
    Tournament::factory()->create(['name' => 'Cup 2025']);

    Livewire::test('pages::archive-show', ['tournament' => $past])
        ->assertOk()
        ->assertSee('Cup 2024')
        ->assertSee('Wertung')
        ->assertSee('Statistik');
});

it('passes poll=false to the embedded read-only components', function () {
    $past = Tournament::factory()->create(['created_at' => now()->subYear()]);
    Tournament::factory()->create();

    Livewire::test('pages::leaderboard', ['tournamentId' => $past->id, 'poll' => false])
        ->assertSet('poll', false)
        ->assertDontSee('wire:poll');
});

it('404s when trying to open the current tournament through the archive', function () {
    $past = Tournament::factory()->create(['created_at' => now()->subYear()]);
    $current = Tournament::factory()->create();

    $this->get(route('archive.show', $current))->assertNotFound();
    $this->get(route('archive.show', $past))->assertOk();
});

it('shows the Archiv sidebar link only when a past tournament exists', function () {
    Tournament::factory()->create(['name' => 'Solo Cup']);

    $this->get('/dashboard')->assertDontSee('Archiv');

    Tournament::factory()->create(['name' => 'Zweiter Cup']);

    $this->get('/dashboard')->assertSee('Archiv');
});
