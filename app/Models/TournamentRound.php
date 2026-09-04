<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TournamentRoundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'round_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'status' => TournamentRoundStatus::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(TournamentFixture::class);
    }

    public function isActive(): bool
    {
        return $this->status === TournamentRoundStatus::ACTIVE;
    }
}
