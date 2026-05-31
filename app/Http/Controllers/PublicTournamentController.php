<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\StatisticsService;

class PublicTournamentController extends Controller
{
    public function __invoke(string $token, StatisticsService $stats)
    {
        $tournament = Tournament::where('public_token', $token)->firstOrFail();

        return view('tournament-public', [
            'tournament' => $tournament,
            'stats' => $tournament->isFinished() ? $stats->summary($tournament) : null,
        ]);
    }
}
