<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user and auto-create their Player record.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::PLAYER,
            ]);

            Player::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'rating' => config('courtly.rating.default_rating', 0.00),
                'rating_status' => 'PROVISIONAL',
                'rating_confidence' => 0.10,
                'rated_games_count' => 0,
                'total_games' => 0,
                'wins' => 0,
                'losses' => 0,
            ]);

            return $user;
        });

        // Queue verification email (non-blocking)
        $user->sendEmailVerificationNotification();

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role', 'email_verified_at']),
                'message' => 'Registration successful. Please check your email to verify your account.',
            ],
        ], 201);
    }

    /**
     * Log in an existing user.
     */
    public function login(Request $request): JsonResponse
    {
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

        $request->session()->regenerate();

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
}
