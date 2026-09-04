<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'start_time',
        'number_of_courts',
        'status',
        'matchmaking_mode',
        'type',
        'created_by',
        'started_at',
        'finished_at',
        'tournament_finished_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime',
            'number_of_courts' => 'integer',
            'status' => SessionStatus::class,
            'type' => SessionType::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'tournament_finished_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function sessionPlayers(): HasMany
    {
        return $this->hasMany(SessionPlayer::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function matchmakingLogs(): HasMany
    {
        return $this->hasMany(MatchmakingLog::class);
    }

    public function tournamentTeams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class);
    }

    public function tournamentRounds(): HasMany
    {
        return $this->hasMany(TournamentRound::class);
    }

    public function isActive(): bool
    {
        return $this->status === SessionStatus::ACTIVE;
    }

    public function isTournament(): bool
    {
        return $this->type === SessionType::TOURNAMENT;
    }

    /**
     * Whether this session runs the traditional peg-board matchmaking.
     * Defaults to 'smart' when the column has not been migrated yet.
     */
    public function usesPegMode(): bool
    {
        return $this->matchmaking_mode === 'peg';
    }

    public function maxGamesPlayed(): int
    {
        return (int) $this->sessionPlayers()->max('games_played');
    }

    /**
     * Whether this session belongs to the given user.
     */
    public function belongsToUser(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id;
    }
}
