<?php

namespace Database\Seeders;

use App\Services\GroupGeneratorService;
use App\Services\KoBracketService;

/**
 * Current tournament mid KO phase: group phase fully played, the quarter-finals
 * decided, and the semi-finals standing but not yet played.
 */
class KOPhaseSeeder extends TournamentSeeder
{
    public function run(): void
    {
        $this->seedArchivedTournament();

        $tournament = $this->createTournament('Bierpong WM '.now()->year);
        $this->seedTablesAndTeams($tournament);

        app(GroupGeneratorService::class)->generate($tournament->fresh());
        $this->finishGroupMatches($tournament->fresh());

        app(KoBracketService::class)->startKoPhase($tournament->fresh());

        // Play out the quarter-finals → the semi-finals get seeded but stay open.
        $this->finishFirstKoRound($tournament->fresh());
    }
}
