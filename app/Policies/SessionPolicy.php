<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Session;
use App\Models\User;

class SessionPolicy
{
    public function view(User $user, Session $session): bool
    {
        return true; // Allow viewing any session (tablet is shared at the venue)
    }

    public function manage(User $user, Session $session): bool
    {
        return $user->id === $session->created_by || $user->isAdmin();
    }
}
