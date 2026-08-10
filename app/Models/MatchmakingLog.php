<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchmakingLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'match_id',
        'algorithm_version',
        'candidate_pool_size',
        'rotation_score',
        'skill_spread',
        'team_balance_difference',
        'repeat_teammate_penalty',
        'recent_teammate_penalty',
        'opponent_penalty',
        'winner_priority_score',
        'group_cost',
        'pairing_cost',
        'total_cost',
        'calculation_time_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'candidate_pool_size' => 'integer',
            'rotation_score' => 'decimal:2',
            'skill_spread' => 'decimal:2',
            'team_balance_difference' => 'decimal:2',
            'repeat_teammate_penalty' => 'decimal:2',
            'recent_teammate_penalty' => 'decimal:2',
            'opponent_penalty' => 'decimal:2',
            'winner_priority_score' => 'decimal:2',
            'group_cost' => 'decimal:2',
            'pairing_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'calculation_time_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
