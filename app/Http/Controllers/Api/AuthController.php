<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Enums\UserRole;
use App\Enums\PlayerGender;
use App\Models\Player;
use App\Models\User;
use App\Services\CircleService;
use App\Services\PlayerAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Register a new user and auto-create their Player record.
     */
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
            'gender' => ['sometimes', 'nullable', Rule::enum(PlayerGender::class)],
            'circle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $circle = null;

        $user = DB::transaction(function () use ($validated, &$circle) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::PLAYER,
            ]);

            $circle = app(CircleService::class)->createPersonalCircle($user, $validated['circle_name'] ?? null);
            app(CircleService::class)->ensureLinkedPlayer($user, $circle, $validated['gender'] ?? null);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role', 'email_verified_at']),
                'circle' => [
                    'id' => $circle->id,
                    'name' => $circle->name,
                ],
                'message' => 'Registration successful. Check your email to verify your account.',
            ],
        ], 201);
    }

    /**
     * Log in an existing user.
     */
    public function login(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! auth()->guard('web')->attempt(
            ['email' => $validated['email'], 'password' => $validated['password']],
            $validated['remember'] ?? false
        )) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'data' => [
                'user' => $request->user()->load('player'),
                'message' => 'Login successful.',
            ],
        ]);
    }

    /**
     * Log out the current user.
     */
    public function logout(Request $request): JsonResponse
    {
        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->load('player'),
        ]);
    }

    /**
     * The member's own overview: every circle they belong to plus
     * account-level overall stats aggregated across those circles.
     */
    public function overview(Request $request, PlayerAnalyticsService $analytics): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'circles' => $user->circles()->get()->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'is_admin' => $c->isAdmin($user),
                ]),
                'overall' => $analytics->buildOverall($user),
            ],
        ]);
    }
}
