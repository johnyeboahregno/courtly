<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionStatus;
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
        'created_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime',
            'number_of_courts' => 'integer',
            'status' => SessionStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->status === SessionStatus::ACTIVE;
    }

    public function maxGamesPlayed(): int
    {
        return (int) $this->sessionPlayers()->max('games_played');
    }
}
