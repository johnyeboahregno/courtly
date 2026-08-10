<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'rating_history';

    protected $fillable = [
        'player_id',
        'match_id',
        'rating_before',
        'rating_after',
        'rating_change',
        'expected_result',
        'actual_result',
        'k_factor',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_before' => 'decimal:2',
            'rating_after' => 'decimal:2',
            'rating_change' => 'decimal:2',
            'expected_result' => 'decimal:4',
            'actual_result' => 'decimal:2',
            'k_factor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
