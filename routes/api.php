<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SessionEventsController;
use App\Http\Controllers\Api\SessionPlayerController;
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

// Public routes (no auth needed — tablet is shared kiosk)
Route::get('/sessions/{session}', [SessionController::class, 'show']);
Route::get('/sessions/{session}/players', [SessionPlayerController::class, 'index']);
Route::get('/sessions/{session}/summary', [SessionController::class, 'summary']);
Route::get('/sessions/{session}/events', SessionEventsController::class);
Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{player}', [PlayerController::class, 'show']);
Route::get('/players/{player}/history', [PlayerController::class, 'history']);
Route::delete('/players/{player}', [PlayerController::class, 'destroy']);
Route::delete('/players/{player}', [PlayerController::class, 'destroy']);

// Tablet kiosk operations (no auth — shared device)
Route::post('/sessions/{session}/start', [SessionController::class, 'start']);
Route::post('/sessions/{session}/pause', [SessionController::class, 'pause']);
Route::post('/sessions/{session}/resume', [SessionController::class, 'resume']);
Route::post('/sessions/{session}/finish', [SessionController::class, 'finish']);
Route::post('/sessions/{session}/players', [SessionPlayerController::class, 'store']);
Route::post('/session-players/{sessionPlayer}/pause', [SessionPlayerController::class, 'pause']);
Route::post('/session-players/{sessionPlayer}/resume', [SessionPlayerController::class, 'resume']);
Route::post('/session-players/{sessionPlayer}/leave', [SessionPlayerController::class, 'leave']);
Route::post('/matches/{match}/result', [MatchController::class, 'recordResult']);
Route::post('/matches/{match}/correct', [MatchController::class, 'correctResult']);

// Create session — kiosk operation (no auth)
Route::post('/sessions', [SessionController::class, 'store']);

// Authenticated routes (admin/organiser account management only)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/email/verification-notification', fn () => response()->json(['message' => 'Verification email resent.']));
    Route::get('/sessions', [SessionController::class, 'index']);
});
