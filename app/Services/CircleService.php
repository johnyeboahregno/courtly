<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CircleJoinRequestStatus;
use App\Enums\CircleVisibility;
use App\Enums\SessionStatus;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\Player;
use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    /**
     * Invite someone to a circle by email. Registered users are added
     * immediately (with a linked player record); unknown addresses receive an
     * email carrying the circle's invite code.
     *
     * @throws \DomainException when the inviter is not the circle admin
     */
    public function inviteByEmail(User $inviter, Circle $circle, string $email): array
    {
        if (! $circle->isAdmin($inviter)) {
            throw new \DomainException('Only the circle owner can invite people.');
        }

        $email = strtolower(trim($email));

        $existing = User::where('email', $email)->first();

        if ($existing) {
            if ($circle->hasMember($existing)) {
                return [
                    'status' => 'already_member',
                    'message' => $existing->name.' is already a member of this circle.',
                ];
            }

            CircleMember::create([
                'circle_id' => $circle->id,
                'user_id' => $existing->id,
            ]);

            $this->ensureLinkedPlayer($existing, $circle);

            return [
                'status' => 'joined',
                'message' => $existing->name.' has been added to '.$circle->name.'.',
            ];
        }

        try {
            Mail::to($email)->send(new \App\Mail\CircleInvite($circle, $inviter));
        } catch (\Throwable $e) {
            Log::warning('circle.invite.email.failed', [
                'circle_id' => $circle->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'status' => 'emailed',
            'message' => 'Invite sent to '.$email.'.',
        ];
    }

    /**
     * Send (or re-send) a request to join a public circle. Idempotent: a
     * previously declined request is flipped back to PENDING.
     *
     * @throws \DomainException when already a member or the circle is private
     */
    public function requestJoin(User $user, Circle $circle): CircleJoinRequest
    {
        if ($circle->hasMember($user)) {
            throw new \DomainException('You are already a member of this circle.');
        }

        if (! $circle->isDiscoverable()) {
            throw new \DomainException('This circle is private — join it with an invite code.');
        }

        $joinRequest = CircleJoinRequest::firstOrNew([
            'circle_id' => $circle->id,
            'user_id' => $user->id,
        ]);

        $joinRequest->status = CircleJoinRequestStatus::PENDING;
        $joinRequest->save();

        return $joinRequest;
    }

    /**
     * Approve a join request: flip to APPROVED, add the membership and a
     * linked player record (idempotent).
     */
    public function approveRequest(CircleJoinRequest $joinRequest): void
    {
        if ($joinRequest->status === CircleJoinRequestStatus::APPROVED) {
            return;
        }

        $joinRequest->status = CircleJoinRequestStatus::APPROVED;
        $joinRequest->save();

        $circle = $joinRequest->circle;
        $user = $joinRequest->user;

        if (! CircleMember::where('circle_id', $circle->id)->where('user_id', $user->id)->exists()) {
            CircleMember::create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
            ]);
        }

        $this->ensureLinkedPlayer($user, $circle);
    }

    /**
     * Decline a join request.
     */
    public function declineRequest(CircleJoinRequest $joinRequest): void
    {
        $joinRequest->status = CircleJoinRequestStatus::DECLINED;
        $joinRequest->save();
    }

    /**
     * Leave a circle. Owners cannot leave their own personal circle.
     *
     * @throws \DomainException when trying to leave your own circle
     */
    public function leaveCircle(User $user, Circle $circle): void
    {
        if ($circle->isAdmin($user)) {
            throw new \DomainException('You cannot leave your own circle.');
        }

        if (! $circle->hasMember($user)) {
            return;
        }

        $circle->members()->detach($user->id);
    }

    /**
     * Update a circle's profile (name, description, visibility, location).
     * Only the admin may call this.
     */
    public function updateCircle(Circle $circle, array $data): Circle
    {
        if (array_key_exists('name', $data)) {
            $desired = trim((string) $data['name']);
            if ($desired !== '' && $desired !== $circle->name) {
                $circle->name = Circle::uniqueName($desired);
            }
        }

        if (array_key_exists('description', $data)) {
            $circle->description = $data['description'] !== null
                ? trim((string) $data['description'])
                : null;
        }

        if (array_key_exists('visibility', $data)) {
            $circle->visibility = CircleVisibility::from($data['visibility']);
        }

        if (array_key_exists('location_label', $data)) {
            $circle->location_label = $data['location_label'] !== null
                ? trim((string) $data['location_label'])
                : null;
        }

        $circle->save();

        return $circle;
    }

    /**
     * Build the social graph backing the interactive circle map: every circle
     * the user belongs to (with member detail), plus every other public circle
     * available to discover and join, plus the user's pending join requests.
     */
    public function buildMapGraph(User $user): array
    {
        $myCircles = $user->circles()->withCount(['members', 'players'])->get();

        $nodes = $myCircles->map(fn (Circle $circle) => $this->circleNode($circle, $user, true))->values()->all();

        Circle::query()
            ->where('visibility', CircleVisibility::PUBLIC->value)
            ->whereNotIn('id', $myCircles->pluck('id')->all())
            ->withCount(['members', 'players'])
            ->orderBy('name')
            ->get()
            ->each(function (Circle $circle) use (&$nodes) {
                $nodes[] = $this->circleNode($circle, null, false);
            });

        $pending = $user->joinRequests()
            ->with('circle')
            ->where('status', CircleJoinRequestStatus::PENDING->value)
            ->get()
            ->map(fn (CircleJoinRequest $r) => [
                'circle_id' => $r->circle_id,
                'circle_name' => $r->circle?->name,
                'status' => $r->status->value,
            ])
            ->values();

        $liveSessions = Session::whereIn('circle_id', $myCircles->pluck('id')->all())
            ->whereIn('status', [SessionStatus::ACTIVE->value, SessionStatus::PAUSED->value])
            ->orderBy('started_at')
            ->get()
            ->map(fn (Session $session) => [
                'id' => (int) $session->id,
                'name' => $session->name,
                'circle_id' => (int) $session->circle_id,
                'circle_name' => $myCircles->firstWhere('id', $session->circle_id)?->name ?? 'Session',
                'status' => $session->status->value,
            ])
            ->values()
            ->all();

        return [
            'personal_circle_id' => (int) ($user->personalCircle?->id ?? 0),
            'nodes' => $nodes,
            'pending_requests' => $pending,
            'live_sessions' => $liveSessions,
        ];
    }

    /**
     * Detailed payload for one circle, including membership state and (for the
     * admin) the invite code. Used by both the map and the single-circle view.
     */
    public function detail(Circle $circle, User $viewer): array
    {
        $isMine = $circle->hasMember($viewer);
        $node = $this->circleNode($circle, $viewer, $isMine);
        $node['is_member'] = $isMine;

        if ($circle->isAdmin($viewer)) {
            $node['invite_code'] = $circle->invite_code;
            $node['pending_requests'] = $circle->joinRequests()
                ->with('user')
                ->where('status', CircleJoinRequestStatus::PENDING->value)
                ->get()
                ->map(fn (CircleJoinRequest $r) => [
                    'id' => $r->id,
                    'user_id' => $r->user_id,
                    'name' => $r->user?->name,
                ])
                ->values();
        }

        $existing = $circle->joinRequests()->where('user_id', $viewer->id)->first();
        $node['join_request_status'] = $existing?->status->value;

        return $node;
    }

    /**
     * The 0–100 engagement score shown on a circle's HUD: members + recent
     * sessions + recent completed matches.
     */
    public function networkScore(Circle $circle): int
    {
        $recent = now()->subDays(30);

        $memberScore = min($circle->members()->count(), 10) * 4;

        $sessionScore = min($circle->sessions()->where('date', '>=', $recent)->count() * 5, 40);

        $matchScore = min(
            (int) $circle->sessions()->where('date', '>=', $recent)->withCount('matches')->get()->sum('matches_count'),
            20,
        );

        return (int) min(100, $memberScore + $sessionScore + $matchScore);
    }

    /**
     * Map a player's rating + games played onto a tier badge name.
     */
    public function playerTier(Player $player): string
    {
        $tiers = config('courtly.circles.tiers', []);
        $rating = (float) $player->rating;
        $games = (int) $player->total_games;
        $tier = 'Rookie';

        foreach ($tiers as $config) {
            if ($rating >= (float) ($config['min_rating'] ?? 0) && $games >= (int) ($config['min_games'] ?? 0)) {
                $tier = (string) $config['name'];
            }
        }

        return $tier;
    }

    /**
     * Serialize a circle into a map node.
     */
    private function circleNode(Circle $circle, ?User $viewer, bool $isMine): array
    {
        $members = [];

        if ($isMine) {
            foreach ($circle->members()->orderBy('name')->get() as $member) {
                $personal = $member->personalCircle;
                $linkedPlayer = Player::where('circle_id', $circle->id)->where('user_id', $member->id)->first();
                $members[] = [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'player_id' => $linkedPlayer?->id,
                    'personal_circle_id' => $personal?->id,
                    'personal_circle_public' => $personal?->isDiscoverable() ?? false,
                ];
            }
        }

        $isAdmin = $viewer !== null && $circle->isAdmin($viewer);

        $pendingRequests = [];
        if ($isAdmin) {
            $pendingRequests = $circle->joinRequests()
                ->with('user')
                ->where('status', CircleJoinRequestStatus::PENDING->value)
                ->get()
                ->map(fn (CircleJoinRequest $r) => [
                    'id' => $r->id,
                    'user_id' => $r->user_id,
                    'name' => $r->user?->name,
                ])
                ->values()
                ->all();
        }

        return [
            'id' => $circle->id,
            'name' => $circle->name,
            'kind' => $isMine ? ($isAdmin ? 'mine' : 'joined') : 'discoverable',
            'is_admin' => $isAdmin,
            'visibility' => $circle->visibility->value,
            'description' => $circle->description,
            'location_label' => $circle->location_label,
            'member_count' => (int) ($circle->members_count ?? $circle->members()->count()),
            'player_count' => (int) ($circle->players_count ?? $circle->players()->count()),
            'network_score' => $this->networkScore($circle),
            'invite_code' => $isAdmin ? $circle->invite_code : null,
            'members' => $members,
            'pending_requests' => $pendingRequests,
        ];
    }
}
