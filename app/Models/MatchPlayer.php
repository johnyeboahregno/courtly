<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'match_id',
        'player_id',
        'team',
        'position',
        'rating_before',
        'rating_after',
        'rating_confidence_before',
        'rating_confidence_after',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'team' => 'integer',
            'position' => 'integer',
            'rating_before' => 'decimal:2',
            'rating_after' => 'decimal:2',
            'rating_confidence_before' => 'decimal:2',
            'rating_confidence_after' => 'decimal:2',
            'result' => MatchResult::class,
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

    public function won(): bool
    {
        return $this->result === MatchResult::WIN;
    }
}
