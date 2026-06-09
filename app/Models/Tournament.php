<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'public_token', 'status', 'group_match_duration_minutes', 'ko_match_duration_minutes', 'count_throws'])]
class Tournament extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'group_match_duration_minutes' => 'integer',
            'ko_match_duration_minutes' => 'integer',
            'count_throws' => 'boolean',
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

    public function isKoPhase(): bool
    {
        return $this->status === 'ko';
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }
}
