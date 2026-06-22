<?php

use App\Http\Controllers\PublicTournamentController;
use App\Models\Tournament;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/t/{token}', PublicTournamentController::class)->name('tournament.public');

Route::view('/regeln', 'rules', ['title' => 'Spielregeln'])->name('rules');

Route::get('/turnierplan', function () {
    $tournament = Tournament::query()->latest()->first();

    abort_unless($tournament && $tournament->hasPublicSchedule(), 404);

    return view('schedule', ['tournament' => $tournament, 'title' => 'Turnierplan']);
})->name('schedule');

Route::middleware('auth')->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('/matches', 'pages::match-list')->name('matches.index');
    Route::livewire('/match/{match}', 'pages::match-score')->name('match.score');
    Route::livewire('/settings', 'pages::settings')->name('settings');

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/setup', 'pages::admin-setup')->name('tournament.setup');
        Route::livewire('/statistics', 'pages::statistics')->name('statistics');
        Route::livewire('/sonderregeln', 'pages::special-rules')->name('special-rules');
        Route::livewire('/archiv', 'pages::archive')->name('archive.index');
        Route::livewire('/archiv/{tournament}', 'pages::archive-show')->name('archive.show');
    });
});
