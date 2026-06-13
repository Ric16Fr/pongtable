<?php

namespace Database\Seeders;

use App\Models\GameMatch;
use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use App\Services\KoBracketService;
use App\Services\MatchResultService;
use Illuminate\Database\Seeder;

/**
 * Shared building blocks for the tournament seeders. Each concrete seeder
 * produces the same 16 teams across 4 tables plus a fully played-out
 * tournament in the archive; they differ only in how far the current
 * tournament has progressed.
 *
 * Results are driven through the real result pipeline
 * ({@see MatchResultService}) so group standings and statistics are computed
 * exactly as they would be in the running app.
 */
abstract class TournamentSeeder extends Seeder
{
    /**
     * The 16 teams every seeded tournament uses.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    protected array $teamData = [
        ['Team Shotgun', '#f59e0b'],
        ['Team Bierherz', '#ef4444'],
        ['Team NullPointer', '#3b82f6'],
        ['Team 404', '#8b5cf6'],
        ['Team Overflowz', '#10b981'],
        ['Team Syntax Error', '#f97316'],
        ['Team Legacy Code', '#64748b'],
        ['Team Hot Fix', '#ec4899'],
        ['Team Segfault', '#06b6d4'],
        ['Team Kernel Panic', '#a855f7'],
        ['Team Deadlock', '#14b8a6'],
        ['Team Race Condition', '#eab308'],
        ['Team Rubber Duck', '#f43f5e'],
        ['Team Merge Conflict', '#22c55e'],
        ['Team Stack Trace', '#0ea5e9'],
        ['Team Off By One', '#d946ef'],
    ];

    /**
     * The four tables every seeded tournament uses.
     *
     * @var array<int, string>
     */
    protected array $tableNames = ['Tisch Rot', 'Tisch Blau', 'Tisch Grün', 'Tisch Gelb'];

    /**
     * Find or create a tournament shell in "setup" status.
     */
    protected function createTournament(string $name): Tournament
    {
        return Tournament::firstOrCreate(
            ['name' => $name],
            [
                'group_match_duration_minutes' => 20,
                'ko_match_duration_minutes' => 20,
                'status' => 'setup',
            ],
        );
    }

    /**
     * Attach the 4 tables and 16 teams to a tournament (idempotent).
     */
    protected function seedTablesAndTeams(Tournament $tournament): void
    {
        if ($tournament->tables()->doesntExist()) {
            foreach ($this->tableNames as $tableName) {
                Table::create(['tournament_id' => $tournament->id, 'name' => $tableName]);
            }
        }

        if ($tournament->teams()->doesntExist()) {
            foreach ($this->teamData as [$name, $color]) {
                Team::create([
                    'tournament_id' => $tournament->id,
                    'name' => $name,
                    'color' => $color,
                ]);
            }
        }
    }

    /**
     * Create a fully played-out tournament from last year for the archive:
     * group phase, KO bracket and final all finished, champion crowned.
     */
    protected function seedArchivedTournament(): Tournament
    {
        $tournament = $this->createTournament('Bierpong WM '.(now()->year - 1));

        if ($tournament->isFinished()) {
            return $tournament;
        }

        $this->seedTablesAndTeams($tournament);

        app(GroupGeneratorService::class)->generate($tournament->fresh());
        $this->finishGroupMatches($tournament->fresh());

        app(KoBracketService::class)->startKoPhase($tournament->fresh());
        $this->finishKoUntilChampion($tournament->fresh());

        return $tournament->fresh();
    }

    /**
     * Play out group matches. With $limitPerGroup set, only the first N matches
     * of each group are finished and the rest stay "pending".
     */
    protected function finishGroupMatches(Tournament $tournament, ?int $limitPerGroup = null): void
    {
        foreach ($tournament->groups()->get() as $group) {
            $matches = $group->matches()->where('phase', 'group')->orderBy('id')->get();

            foreach ($matches->values() as $index => $match) {
                if ($limitPerGroup !== null && $index >= $limitPerGroup) {
                    break;
                }

                $this->playMatch($match, 'group');
            }
        }
    }

    /**
     * Finish every pending KO match of a single round, so the next round gets
     * populated by the bracket service but stays unplayed.
     */
    protected function finishKoRound(Tournament $tournament, int $koRound): void
    {
        $matches = $tournament->matches()
            ->where('phase', 'ko')
            ->where('ko_round', $koRound)
            ->where('status', 'pending')
            ->orderBy('ko_position')
            ->get();

        foreach ($matches as $match) {
            $this->playMatch($match, 'ko');
        }
    }

    /**
     * Finish the current top KO round (e.g. the quarter-finals), leaving the
     * round above it standing but unplayed.
     */
    protected function finishFirstKoRound(Tournament $tournament): void
    {
        $round = (int) $tournament->matches()->where('phase', 'ko')->max('ko_round');

        if ($round > 0) {
            $this->finishKoRound($tournament, $round);
        }
    }

    /**
     * Keep playing KO rounds until the final is decided and the tournament
     * flips to "finished".
     */
    protected function finishKoUntilChampion(Tournament $tournament): void
    {
        while ($tournament->fresh()->isKoPhase()) {
            $pending = $tournament->matches()
                ->where('phase', 'ko')
                ->where('status', 'pending')
                ->whereNotNull('home_team_id')
                ->whereNotNull('away_team_id')
                ->orderBy('ko_round', 'desc')
                ->orderBy('ko_position')
                ->get();

            if ($pending->isEmpty()) {
                break;
            }

            foreach ($pending as $match) {
                $this->playMatch($match, 'ko');
            }
        }
    }

    /**
     * Generate a plausible result for a single match and finalize it.
     */
    protected function playMatch(GameMatch $match, string $phase): void
    {
        $homeWins = (bool) random_int(0, 1);

        // The winner clears all 10 cups; the loser ends somewhere short.
        $winnerCups = 10;
        $loserCups = random_int(3, 8);

        // Winners tend to be more efficient (fewer throws per cup).
        $winnerThrows = random_int(13, 20);
        $loserThrows = random_int(18, 30);

        $duration = $phase === 'ko' ? random_int(300, 780) : random_int(150, 540);

        $this->finishMatch(
            $match,
            homeCups: $homeWins ? $winnerCups : $loserCups,
            awayCups: $homeWins ? $loserCups : $winnerCups,
            homeThrows: $homeWins ? $winnerThrows : $loserThrows,
            awayThrows: $homeWins ? $loserThrows : $winnerThrows,
            homePenalty: random_int(0, 2),
            awayPenalty: random_int(0, 2),
            durationSeconds: $duration,
        );
    }

    /**
     * Drive a single match through the real result pipeline (pre-entry →
     * active → scoring → finished) so standings and statistics end up
     * identical to a match played in the running app.
     */
    protected function finishMatch(
        GameMatch $match,
        int $homeCups,
        int $awayCups,
        int $homeThrows,
        int $awayThrows,
        int $homePenalty,
        int $awayPenalty,
        int $durationSeconds,
    ): void {
        $service = app(MatchResultService::class);

        $service->startMatch($match, [
            'home_throws' => $homeThrows,
            'home_penalty_cups' => $homePenalty,
            'away_throws' => $awayThrows,
            'away_penalty_cups' => $awayPenalty,
        ]);

        // Backdate the start so endTimer derives a realistic duration.
        $match->update(['started_at' => now()->subSeconds($durationSeconds)]);

        $service->endTimer($match->fresh());
        $service->saveResult($match->fresh(), $homeCups, $awayCups);
    }
}
