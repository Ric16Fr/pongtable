<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tournament_id',
    'phase',
    'group_id',
    'ko_round',
    'ko_position',
    'table_id',
    'home_team_id',
    'away_team_id',
    'winner_team_id',
    'status',
    'started_at',
    'ended_at',
])]
class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(MatchStat::class, 'match_id');
    }

    public function homeStat(): ?MatchStat
    {
        return $this->stats->firstWhere('team_id', $this->home_team_id);
    }

    public function awayStat(): ?MatchStat
    {
        return $this->stats->firstWhere('team_id', $this->away_team_id);
    }
}
