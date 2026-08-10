<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;

class MatchPolicy
{
    public function recordResult(User $user, GameMatch $match): bool
    {
        return $user->id === $match->session->created_by || $user->isAdmin();
    }
}
