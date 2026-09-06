<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\Player;
use App\Services\CircleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CircleController extends Controller
{
    /**
     * List every circle the authenticated user belongs to.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $circles = $user->circles()->withCount(['members', 'players'])->get()->map(fn (Circle $circle) => [
            'id' => $circle->id,
            'name' => $circle->name,
            'is_admin' => $circle->isAdmin($user),
            'invite_code' => $circle->isAdmin($user) ? $circle->invite_code : null,
            'visibility' => $circle->visibility->value,
            'description' => $circle->description,
            'location_label' => $circle->location_label,
            'member_count' => $circle->members_count,
            'player_count' => $circle->players_count,
        ]);

        return response()->json(['data' => $circles]);
    }

    /**
     * The interactive map: my circles (with members) + discoverable public
     * circles + my pending join requests.
     */
    public function map(Request $request, CircleService $service): JsonResponse
    {
        return response()->json(['data' => $service->buildMapGraph($request->user())]);
    }

    /**
     * Detail for a single circle. Members and admins get the full picture;
     * non-members may only view public circles.
     */
    public function show(Request $request, Circle $circle, CircleService $service): JsonResponse
    {
        $user = $request->user();

        if (! $circle->hasMember($user) && ! $circle->isDiscoverable()) {
            abort(403, 'You do not have access to this circle.');
        }

        return response()->json(['data' => $service->detail($circle, $user)]);
    }

    /**
     * Update a circle's profile. Admin only.
     */
    public function update(Request $request, Circle $circle, CircleService $service): JsonResponse
    {
        $user = $request->user();

        if (! $circle->isAdmin($user)) {
            abort(403, 'Only the circle owner can edit this circle.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'visibility' => ['sometimes', 'string', 'in:PUBLIC,PRIVATE'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $circle = $service->updateCircle($circle, $validated);

        return response()->json(['data' => $service->detail($circle, $user)]);
    }

    /**
     * Join a circle by its invite code (instant, approval-free).
     */
    public function join(Request $request, CircleService $service): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'max:32'],
        ]);

        try {
            $circle = $service->joinByInviteCode($request->user(), $validated['invite_code']);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Invalid invite code.'], 404);
        }

        return response()->json([
            'data' => [
                'circle' => [
                    'id' => $circle->id,
                    'name' => $circle->name,
                ],
            ],
        ], 201);
    }

    /**
     * Ask to join a public circle. The admin approves or declines.
     */
    public function requestJoin(Request $request, Circle $circle, CircleService $service): JsonResponse
    {
        try {
            $joinRequest = $service->requestJoin($request->user(), $circle);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'status' => $joinRequest->status->value,
                'circle_id' => $circle->id,
            ],
        ], 201);
    }

    /**
     * Approve a join request. Admin only.
     */
    public function approve(Request $request, CircleJoinRequest $joinRequest, CircleService $service): JsonResponse
    {
        $this->authorizeRequestAdmin($request, $joinRequest);

        $service->approveRequest($joinRequest);

        return response()->json(['data' => ['status' => 'APPROVED']]);
    }

    /**
     * Decline a join request. Admin only.
     */
    public function decline(Request $request, CircleJoinRequest $joinRequest, CircleService $service): JsonResponse
    {
        $this->authorizeRequestAdmin($request, $joinRequest);

        $service->declineRequest($joinRequest);

        return response()->json(['data' => ['status' => 'DECLINED']]);
    }

    /**
     * Leave a circle. Owners cannot leave their own circle.
     */
    public function leave(Request $request, Circle $circle, CircleService $service): JsonResponse
    {
        try {
            $service->leaveCircle($request->user(), $circle);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * Circle-scoped leaderboard with tier badges.
     */
    public function leaderboard(Request $request, Circle $circle, CircleService $service): JsonResponse
    {
        $user = $request->user();

        if (! $circle->hasMember($user) && ! $circle->isDiscoverable()) {
            abort(403, 'You do not have access to this circle.');
        }

        $players = Player::where('circle_id', $circle->id)
            ->orderByDesc('rating')
            ->orderByDesc('total_games')
            ->orderBy('name')
            ->get()
            ->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'rating' => $player->rating,
                'total_games' => $player->total_games,
                'wins' => $player->wins,
                'losses' => $player->losses,
                'tier' => $service->playerTier($player),
            ]);

        return response()->json(['data' => $players]);
    }

    /**
     * Ensure the authenticated user is the admin of the request's circle.
     */
    private function authorizeRequestAdmin(Request $request, CircleJoinRequest $joinRequest): void
    {
        abort_unless(
            $joinRequest->circle->isAdmin($request->user()),
            403,
            'Only the circle owner can manage join requests.'
        );
    }
}
