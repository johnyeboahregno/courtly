<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Circle lifecycle helpers: personal-circle creation, linked player records,
 * and invite-code joining.
 */
class CircleService
{
    /**
     * Create the user's personal circle and make them a member.
     * The requested name (if any) always has " Circle" appended, then is
     * disambiguated to remain unique.
     */
    public function createPersonalCircle(User $user, ?string $requestedName = null): Circle
    {
        $base = trim((string) $requestedName);

        $desired = $base !== ''
            ? $base.' Circle'
            : $user->name."'s Circle";

        $circle = Circle::create([
            'name' => Circle::uniqueName($desired),
            'admin_id' => $user->id,
            'invite_code' => Circle::generateInviteCode(),
        ]);

        CircleMember::create([
            'circle_id' => $circle->id,
            'user_id' => $user->id,
        ]);

        return $circle;
    }

    /**
     * Ensure a player record linked to the account exists in the given circle.
     * Returns the existing record when one is already present.
     */
    public function ensureLinkedPlayer(User $user, Circle $circle, ?string $gender = null): Player
    {
        $player = Player::where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $player) {
            $player = Player::create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'gender' => $gender,
                'rating' => config('courtly.rating.default_rating', 0.00),
                'rating_status' => 'PROVISIONAL',
                'rating_confidence' => config('courtly.rating.initial_confidence', 0.10),
            ]);
        }

        return $player;
    }

    /**
     * Join a circle by its invite code. Creates the membership and a fresh
     * per-circle player record linked to the account. Idempotent for existing
     * members.
     *
     * @throws ModelNotFoundException when the code does not exist
     */
    public function joinByInviteCode(User $user, string $code): Circle
    {
        $circle = Circle::where('invite_code', strtoupper(trim($code)))->firstOrFail();

        if (! CircleMember::where('circle_id', $circle->id)->where('user_id', $user->id)->exists()) {
            CircleMember::create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
            ]);

            $this->ensureLinkedPlayer($user, $circle);
        }

        return $circle;
    }
}
