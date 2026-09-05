<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SessionEventsController;
use App\Http\Controllers\Api\SessionPlayerController;
use App\Http\Controllers\Api\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Courtly
|--------------------------------------------------------------------------
*/

// Public auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', fn () => response()->json(['message' => 'Password reset link sent.']));
Route::post('/reset-password', fn () => response()->json(['message' => 'Password reset.']));

// ── Authenticated (multi-tenant) routes ─────────────────────────────
// Every resource below is scoped to the authenticated user. Sessions,
// players, and matches are only accessible by their owner.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/email/verification-notification', fn () => response()->json(['message' => 'Verification email resent.']));

    // Sessions
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions', [SessionController::class, 'store']);
    Route::get('/sessions/{session}', [SessionController::class, 'show']);
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy']);
    Route::post('/sessions/{session}/start', [SessionController::class, 'start']);
    Route::post('/sessions/{session}/pause', [SessionController::class, 'pause']);
    Route::post('/sessions/{session}/resume', [SessionController::class, 'resume']);
    Route::post('/sessions/{session}/finish', [SessionController::class, 'finish']);
    Route::post('/sessions/{session}/matchmaking-mode', [SessionController::class, 'setMatchmakingMode']);
    Route::post('/sessions/{session}/fill', [SessionController::class, 'fill']);
    Route::post('/sessions/{session}/manual-assignment', [SessionController::class, 'manualAssignment']);
    Route::patch('/sessions/{session}/courts', [SessionController::class, 'adjustCourts']);
    Route::get('/sessions/{session}/summary', [SessionController::class, 'summary']);
    Route::get('/sessions/{session}/events', SessionEventsController::class);

    // Tournament mode: teams can be previewed/edited before Start
    Route::get('/sessions/{session}/tournament/teams', [TournamentController::class, 'teams']);
    Route::post('/sessions/{session}/tournament/teams/regenerate', [TournamentController::class, 'regenerate']);
    Route::post('/sessions/{session}/tournament/teams/swap', [TournamentController::class, 'swap']);

    // Session players
    Route::get('/sessions/{session}/players', [SessionPlayerController::class, 'index']);
    Route::post('/sessions/{session}/players', [SessionPlayerController::class, 'store']);
    Route::post('/session-players/{sessionPlayer}/pause', [SessionPlayerController::class, 'pause']);
    Route::post('/session-players/{sessionPlayer}/resume', [SessionPlayerController::class, 'resume']);
    Route::post('/session-players/{sessionPlayer}/leave', [SessionPlayerController::class, 'leave']);

    // Matches
    Route::post('/matches/{match}/result', [MatchController::class, 'recordResult']);
    Route::post('/matches/{match}/correct', [MatchController::class, 'correctResult']);

    // Players
    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/players/{player}/stats', [PlayerController::class, 'stats']);
    Route::get('/players/{player}/history', [PlayerController::class, 'history']);
    Route::get('/players/{player}', [PlayerController::class, 'show']);
    Route::patch('/players/{player}', [PlayerController::class, 'update']);
    Route::post('/players/{player}/reset-rating', [PlayerController::class, 'resetRating']);
    Route::delete('/players/{player}', [PlayerController::class, 'destroy']);
});
