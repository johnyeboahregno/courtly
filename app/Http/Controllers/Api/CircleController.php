<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Circle;
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

        $circles = $user->circles()->get()->map(fn (Circle $circle) => [
            'id' => $circle->id,
            'name' => $circle->name,
            'is_admin' => $circle->isAdmin($user),
            'invite_code' => $circle->isAdmin($user) ? $circle->invite_code : null,
            'player_count' => $circle->players()->count(),
        ]);

        return response()->json(['data' => $circles]);
    }

    /**
     * Join a circle by its invite code.
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
}
