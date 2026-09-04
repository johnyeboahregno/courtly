<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'name',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function teamPlayers(): HasMany
    {
        return $this->hasMany(TournamentTeamPlayer::class);
    }

    public function players()
    {
        return $this->hasManyThrough(Player::class, TournamentTeamPlayer::class, 'tournament_team_id', 'id', 'id', 'player_id');
    }
}
