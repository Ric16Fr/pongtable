<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\FinalStandingsService;
use App\Services\StatisticsService;
use Livewire\Livewire;
use Spatie\LaravelPdf\Facades\Pdf;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * Placement pages (one per team) plus one special-prize page per award subject.
 */
function expectedCertificateCount(Tournament $tournament): int
{
    $placements = app(FinalStandingsService::class)->standings($tournament)->count();
    $specials = collect(app(StatisticsService::class)->awards($tournament))
        ->sum(fn (array $award) => count($award['subjects']));

    return $placements + $specials;
}

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

it('renders one valid PDF page per placement and special prize', function () {
    $tournament = buildFinishedTournament(4);

    $download = Livewire::test('pages::admin-setup')
        ->call('generateCertificates')
        ->effects['download'] ?? null;

    expect($download)->not->toBeNull();

    $content = base64_decode($download['content']);

    // A real PDF starts with the "%PDF" magic bytes...
    expect($content)->toStartWith('%PDF');

    // ...and carries one /Page object per certificate (the /Pages tree node
    // aside). dompdf does not compress the object structure, so the markers
    // are plain text.
    $pageObjects = substr_count($content, '/Type /Page')
        - substr_count($content, '/Type /Pages');
    expect($pageObjects)->toBe(expectedCertificateCount($tournament));
});

it('hands placement and special-prize certificates to the PDF view (faked)', function () {
    Pdf::fake();
    $tournament = buildFinishedTournament(4);

    Livewire::test('pages::admin-setup')
        ->call('generateCertificates')
        ->assertHasNoErrors();

    $specialCount = collect(app(StatisticsService::class)->awards($tournament))
        ->sum(fn (array $award) => count($award['subjects']));

    Pdf::assertRespondedWithPdf(function ($pdf) use ($specialCount) {
        $certificates = collect($pdf->viewData['certificates']);

        return $pdf->viewName === 'pdf.certificates'
            && $certificates->where('type', 'placement')->count() === 4
            && $certificates->whereIn('type', ['special', 'cup_king'])->count() === $specialCount;
    });
});

it('passes the hide-circles flag from the tournament to the PDF view', function () {
    Pdf::fake();
    $tournament = buildFinishedTournament(4);
    $tournament->update(['hide_certificate_circles' => true]);

    Livewire::test('pages::admin-setup')
        ->call('generateCertificates')
        ->assertHasNoErrors();

    Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->viewData['hideCircles'] === true);
});

it('renders the decorative corner circles by default', function () {
    $html = view('pdf.certificates', [
        'tournamentName' => 'Test Cup',
        'totalCups' => 100,
        'certificates' => [['type' => 'placement', 'team' => 'Die Bierhäscher', 'rank' => 1]],
    ])->render();

    expect($html)->toContain('class="corner corner-blue"')
        ->and($html)->toContain('class="corner corner-red"');
});

it('omits the decorative corner circles when hideCircles is set', function () {
    $html = view('pdf.certificates', [
        'tournamentName' => 'Test Cup',
        'totalCups' => 100,
        'certificates' => [['type' => 'placement', 'team' => 'Die Bierhäscher', 'rank' => 1]],
        'hideCircles' => true,
    ])->render();

    expect($html)->not->toContain('class="corner corner-blue"')
        ->and($html)->not->toContain('class="corner corner-red"');
});

it('renders every certificate type into a valid multi-page PDF', function () {
    $pdf = Pdf::view('pdf.certificates', [
        'tournamentName' => 'Test Cup',
        'totalCups' => 100,
        'certificates' => [
            ['type' => 'placement', 'team' => 'Die Bierhäscher', 'rank' => 1],
            ['type' => 'special', 'team' => 'Pong Profis', 'award' => 'Schluck-Olymp'],
            ['type' => 'cup_king', 'player' => 'Zoe Wurf'],
        ],
    ]);

    $content = $pdf->toResponse(request())->getContent();

    expect($content)->toStartWith('%PDF');

    $pageObjects = substr_count($content, '/Type /Page')
        - substr_count($content, '/Type /Pages');
    expect($pageObjects)->toBe(3);
});

it('confirms the download start with a toast', function () {
    buildFinishedTournament(4);

    Livewire::test('pages::admin-setup')
        ->call('generateCertificates')
        ->assertDispatched('toast-show');
});
