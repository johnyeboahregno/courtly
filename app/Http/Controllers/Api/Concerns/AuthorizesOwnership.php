<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Player;
use App\Models\Session;
use App\Models\User;

trait AuthorizesOwnership
{
    /**
     * The authenticated user for the current request.
     */
    protected function currentUser(): User
    {
        return request()->user();
    }

    /**
     * Ensure the authenticated user owns the given session.
     */
    protected function authorizeSession(Session $session): void
    {
        abort_unless(
            $session->belongsToUser($this->currentUser()),
            403,
            'You do not have access to this session.'
        );
    }

    /**
     * Ensure the authenticated user owns the given player.
     */
    protected function authorizePlayer(Player $player): void
    {
        abort_unless(
            $player->belongsToUser($this->currentUser()),
            403,
            'You do not have access to this player.'
        );
    }
}
