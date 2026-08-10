<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchResult;
use App\Enums\SessionPlayerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionPlayer extends Model
{
    public $timestamps = false;
    use HasFactory;

    protected $fillable = [
        'session_id',
        'player_id',
        'status',
        'games_played',
        'wins',
        'losses',
        'consecutive_games',
        'waiting_since',
        'last_played_at',
        'joined_at',
        'left_at',
        'last_result',
    ];

    protected function casts(): array
    {
        return [
            'status' => SessionPlayerStatus::class,
            'games_played' => 'integer',
            'wins' => 'integer',
            'losses' => 'integer',
            'consecutive_games' => 'integer',
            'waiting_since' => 'datetime',
            'last_played_at' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'last_result' => MatchResult::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function isWaiting(): bool
    {
        return $this->status === SessionPlayerStatus::WAITING;
    }

    public function isPlaying(): bool
    {
        return $this->status === SessionPlayerStatus::PLAYING;
    }

    public function isPaused(): bool
    {
        return $this->status === SessionPlayerStatus::PAUSED;
    }

    public function hasLeft(): bool
    {
        return $this->status === SessionPlayerStatus::LEFT;
    }

    public function satOutLastRound(): bool
    {
        return $this->consecutive_games === 0 && $this->games_played > 0;
    }
}
