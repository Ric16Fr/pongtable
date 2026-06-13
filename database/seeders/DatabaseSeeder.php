<?php

namespace Database\Seeders;

/**
 * Default seeder: a finished tournament in the archive plus a fresh current
 * tournament still in "setup" — teams created, no groups drawn yet.
 */
class DatabaseSeeder extends TournamentSeeder
{
    public function run(): void
    {
        $this->seedArchivedTournament();

        $tournament = $this->createTournament('Bierpong WM '.now()->year);
        $this->seedTablesAndTeams($tournament);
    }
}
