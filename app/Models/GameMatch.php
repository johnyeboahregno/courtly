<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'session_id',
        'court_id',
        'game_number',
        'status',
        'winning_team',
        'close_game',
        'team_1_score',
        'team_2_score',
        'team_1_rating',
        'team_2_rating',
        'team_balance_difference',
        'skill_spread',
        'match_quality',
        'algorithm_version',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'game_number' => 'integer',
            'status' => MatchStatus::class,
            'winning_team' => 'integer',
            'close_game' => 'boolean',
            'team_1_score' => 'integer',
            'team_2_score' => 'integer',
            'team_1_rating' => 'decimal:2',
            'team_2_rating' => 'decimal:2',
            'team_balance_difference' => 'decimal:2',
            'skill_spread' => 'decimal:2',
            'match_quality' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, "match_id");
    }

    public function ratingHistory(): HasMany
    {
        return $this->hasMany(RatingHistory::class, "match_id");
    }

    public function matchmakingLog(): HasOne
    {
        return $this->hasOne(MatchmakingLog::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(MatchFeedback::class, "match_id");
    }

    public function isPlaying(): bool
    {
        return $this->status === MatchStatus::PLAYING;
    }

    public function isCompleted(): bool
    {
        return $this->status === MatchStatus::COMPLETED;
    }
}
