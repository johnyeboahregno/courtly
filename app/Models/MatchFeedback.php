<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackRating;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchFeedback extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'match_id',
        'player_id',
        'quality_rating',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quality_rating' => FeedbackRating::class,
            'created_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
