<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TournamentFixtureStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentFixture extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_round_id',
        'home_team_id',
        'away_team_id',
        'match_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => TournamentFixtureStatus::class,
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(TournamentRound::class, 'tournament_round_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'away_team_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function isBye(): bool
    {
        return $this->status === TournamentFixtureStatus::BYE;
    }
}
