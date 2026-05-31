<?php

use App\Http\Controllers\PublicTournamentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/t/{token}', PublicTournamentController::class)->name('tournament.public');

Route::middleware('auth')->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('/matches', 'pages::match-list')->name('matches.index');
    Route::livewire('/match/{match}', 'pages::match-score')->name('match.score');

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/setup', 'pages::admin-setup')->name('tournament.setup');
        Route::livewire('/statistics', 'pages::statistics')->name('statistics');
    });
});
