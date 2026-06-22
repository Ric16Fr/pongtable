<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'public_token', 'status', 'group_match_duration_minutes', 'ko_match_duration_minutes', 'count_throws', 'play_placement_matches', 'ko_sudden_death', 'determine_cup_king', 'hide_certificate_circles', 'show_schedule', 'schedule'])]
class Tournament extends Model
{
    use HasFactory;

    /**
     * Default hero description shown when no custom description has been set.
     */
    public const DEFAULT_DESCRIPTION = 'Selbst gehostete Bierpong-Turnierverwaltung. Gruppen, KO-Bracket, Live-Timer und eine Bracket-Ansicht für die Großleinwand.';

    protected function casts(): array
    {
        return [
            'group_match_duration_minutes' => 'integer',
            'ko_match_duration_minutes' => 'integer',
            'count_throws' => 'boolean',
            'play_placement_matches' => 'boolean',
            'ko_sudden_death' => 'boolean',
            'determine_cup_king' => 'boolean',
            'hide_certificate_circles' => 'boolean',
            'show_schedule' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tournament $tournament) {
            if (empty($tournament->public_token)) {
                $tournament->public_token = (string) Str::uuid();
            }
        });
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function isSetup(): bool
    {
        return $this->status === 'setup';
    }

    public function isGroupPhase(): bool
    {
        return $this->status === 'group';
    }

    public function isPlacementPhase(): bool
    {
        return $this->status === 'placement';
    }

    public function isKoPhase(): bool
    {
        return $this->status === 'ko';
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    /**
     * Whether the public "Turnierplan" header tab should be shown — i.e. the
     * latest tournament has a publicly visible schedule.
     */
    public static function publicScheduleVisible(): bool
    {
        return (bool) static::query()->latest()->first()?->hasPublicSchedule();
    }

    /**
     * Whether the informational tournament schedule should be publicly
     * visible: the option is on and the schedule text actually has content.
     */
    public function hasPublicSchedule(): bool
    {
        return $this->show_schedule && filled($this->schedule) && count($this->scheduleEntries()) > 0;
    }

    /**
     * Parse the free-text schedule into one entry per non-empty line, each a
     * list of the semicolon-separated team names on that line.
     *
     * @return list<list<string>>
     */
    public function scheduleEntries(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->schedule))
            ->map(fn (string $line) => collect(explode(';', $line))
                ->map(fn (string $team) => trim($team))
                ->filter()
                ->values()
                ->all())
            ->filter(fn (array $teams) => count($teams) > 0)
            ->values()
            ->all();
    }
}
