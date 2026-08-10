<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIRun extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'match_id',
        'run_type',
        'provider',
        'model',
        'input_summary',
        'output',
        'latency_ms',
        'status',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'input_summary' => 'json',
            'output' => 'json',
            'latency_ms' => 'integer',
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
