<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CourtStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'court_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'court_number' => 'integer',
            'status' => CourtStatus::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === CourtStatus::AVAILABLE;
    }
}
