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
            $session->isAccessibleBy($this->currentUser()),
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
            $player->isAccessibleBy($this->currentUser()),
            403,
            'You do not have access to this player.'
        );
    }

    /**
     * Ensure the authenticated user can manage (rename, reset, delete) the
     * given player — reserved for the circle owner, unlike read access which
     * is granted to every circle member.
     */
    protected function authorizePlayerManagement(Player $player): void
    {
        abort_unless(
            $player->isManageableBy($this->currentUser()),
            403,
            'Only the circle owner can manage players.'
        );
    }
}
