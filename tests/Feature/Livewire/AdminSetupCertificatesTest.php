<?php

use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('offers certificate generation once the tournament is finished', function () {
    Tournament::factory()->create(['status' => 'finished']);

    Livewire::test('pages::admin-setup')
        ->assertSee('Urkunden generieren');
});

it('does not offer certificate generation during setup', function () {
    Tournament::factory()->create(['status' => 'setup']);

    Livewire::test('pages::admin-setup')
        ->assertDontSee('Urkunden generieren');
});

it('streams a downloadable certificate PDF named after the tournament', function () {
    buildFinishedTournament(4);

    $name = Tournament::query()->latest()->first()->name;
    $expectedFile = 'urkunden_'.str_replace(' ', '_', $name).'.pdf';

    Livewire::test('pages::admin-setup')
        ->call('generateCertificates')
        ->assertHasNoErrors()
        ->assertFileDownloaded($expectedFile, contentType: 'application/pdf');
});

it('renders one valid PDF page per team', function () {
    buildFinishedTournament(4);

    $download = Livewire::test('pages::admin-setup')
        ->call('generateCertificates')
        ->effects['download'] ?? null;

    expect($download)->not->toBeNull();

    $content = base64_decode($download['content']);

    // A real PDF starts with the "%PDF" magic bytes...
    expect($content)->toStartWith('%PDF');

    // ...and contains one /Page object per team (the /Pages tree node aside).
    // dompdf does not compress the object structure, so the markers are plain.
    $pageObjects = substr_count($content, '/Type /Page')
        - substr_count($content, '/Type /Pages');
    expect($pageObjects)->toBe(4);
});

it('confirms the download start with a toast', function () {
    buildFinishedTournament(4);

    Livewire::test('pages::admin-setup')
        ->call('generateCertificates')
        ->assertDispatched('toast-show');
});
