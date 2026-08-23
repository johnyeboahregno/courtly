<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RatingStatus;
use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'rating',
        'rating_status',
        'rating_confidence',
        'rated_games_count',
        'total_games',
        'wins',
        'losses',
        'consecutive_wins',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'rating_status' => RatingStatus::class,
            'rating_confidence' => 'decimal:2',
            'rated_games_count' => 'integer',
            'total_games' => 'integer',
            'wins' => 'integer',
            'losses' => 'integer',
            'consecutive_wins' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessionPlayers(): HasMany
    {
        return $this->hasMany(SessionPlayer::class);
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function ratingHistory(): HasMany
    {
        return $this->hasMany(RatingHistory::class);
    }

    public function isProvisional(): bool
    {
        return $this->rating_status === RatingStatus::PROVISIONAL;
    }

    public function winPercentage(): float
    {
        if ($this->total_games === 0) {
            return 0.0;
        }

        return round(($this->wins / $this->total_games) * 100, 1);
    }

    /**
     * Whether this player is currently on court in a PLAYING match.
     */
    public function isInActiveMatch(): bool
    {
        return $this->matchPlayers()
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::PLAYING->value))
            ->exists();
    }

    /**
     * Whether this player belongs to the given user.
     */
    public function belongsToUser(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }
}
