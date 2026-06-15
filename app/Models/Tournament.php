<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'public_token', 'status', 'group_match_duration_minutes', 'ko_match_duration_minutes', 'count_throws', 'play_placement_matches', 'ko_sudden_death', 'determine_cup_king'])]
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
}
