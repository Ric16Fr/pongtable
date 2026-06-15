<?php

namespace Database\Seeders;

use App\Services\GroupGeneratorService;

/**
 * Current tournament mid group phase: groups drawn, the first matches of each
 * group already finished, the rest still open.
 */
class GroupPhaseSeeder extends TournamentSeeder
{
    public function run(): void
    {
        $this->seedReferees(4);

        $this->seedArchivedTournament();

        $tournament = $this->createTournament('Bierpong WM '.now()->year);
        $this->seedTablesAndTeams($tournament);

        app(GroupGeneratorService::class)->generate($tournament->fresh());

        // 4 of each group's 6 round-robin matches are done; 2 stay open.
        $this->finishGroupMatches($tournament->fresh(), limitPerGroup: 4);
    }
}
