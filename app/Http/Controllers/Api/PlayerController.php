<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Services\PlayerAnalyticsService;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlayerController extends Controller
{
    use AuthorizesOwnership;

    /**
     * List the authenticated user's players (for selecting to add to a session).
     */
    public function index(): JsonResponse
    {
        $players = Player::query()
            ->where('user_id', $this->currentUser()->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'rating' => (float) $p->rating,
                'total_games' => $p->total_games,
                'is_playing' => $p->isInActiveMatch(),
            ]);

        return response()->json(['data' => $players]);
    }

    /**
     * Get player profile with stats and recent matches.
     */
    public function show(Player $player): JsonResponse
    {
        $this->authorizePlayer($player);

        $player->load('user');

        $recentMatches = $player->matchPlayers()
            ->with(['match.session', 'match.matchPlayers.player'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($mp) => [
                'match_id' => $mp->match_id,
                'date' => $mp->match->session->date,
                'session_name' => $mp->match->session->name,
                'result' => $mp->result,
                'rating_change' => $mp->rating_after !== null
                    ? round($mp->rating_after - $mp->rating_before, 2)
                    : null,
            ]);

        return response()->json([
            'data' => [
                'id' => $player->id,
                'name' => $player->name,
                'rating' => (int) round($player->rating),
                'rating_status' => $player->rating_status,
                'rating_confidence' => $player->rating_confidence,
                'total_games' => $player->total_games,
                'wins' => $player->wins,
                'losses' => $player->losses,
                'win_percentage' => $player->winPercentage(),
                'recent_matches' => $recentMatches,
            ],
        ]);
    }

    /**
     * Get the full analytics payload for the stats screen: rating time
     * series plus derived performance metrics.
     */
    public function stats(Player $player, PlayerAnalyticsService $service): JsonResponse
    {
        $this->authorizePlayer($player);

        return response()->json([
            'data' => $service->build($player),
        ]);
    }

    /**
     * Get player rating history.
     */
    public function history(Player $player): JsonResponse
    {
        $this->authorizePlayer($player);

        return response()->json([
            'data' => $player->ratingHistory()
                ->with('match.session')
                ->latest()
                ->paginate(30),
        ]);
    }

    /**
     * Rename a player. Blocked while the player is on court.
     */
    public function update(Request $request, Player $player): JsonResponse
    {
        $this->authorizePlayer($player);

        if ($player->isInActiveMatch()) {
            return response()->json([
                'message' => 'This player is currently on court and cannot be edited.',
            ], 409);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('players', 'name')
                    ->where('user_id', $this->currentUser()->id)
                    ->ignore($player->id),
            ],
        ]);

        $player->update(['name' => $validated['name']]);

        return response()->json([
            'data' => [
                'id' => $player->id,
                'name' => $player->name,
            ],
        ]);
    }

    /**
     * Reset a player's rating, game stats and history to their starting values.
     */
    public function resetRating(Player $player): JsonResponse
    {
        $this->authorizePlayer($player);

        if ($player->isInActiveMatch()) {
            return response()->json([
                'message' => 'This player is currently on court and cannot be reset.',
            ], 409);
        }

        \Illuminate\Support\Facades\DB::transaction(fn () => $this->applyReset($player));

        return response()->json([
            'message' => 'Player reset.',
            'data' => [
                'id' => $player->id,
                'rating' => (float) $player->rating,
                'rating_status' => $player->rating_status,
                'rating_confidence' => (float) $player->rating_confidence,
                'rated_games_count' => $player->rated_games_count,
                'total_games' => $player->total_games,
                'wins' => $player->wins,
                'losses' => $player->losses,
            ],
        ]);
    }

    /**
     * Reset every player in the user's roster back to their starting values.
     * Players currently on court are skipped.
     */
    public function resetAll(): JsonResponse
    {
        $players = Player::where('user_id', $this->currentUser()->id)->get();

        $reset = 0;
        $skipped = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($players, &$reset, &$skipped) {
            foreach ($players as $player) {
                if ($player->isInActiveMatch()) {
                    $skipped++;
                    continue;
                }

                $this->applyReset($player);
                $reset++;
            }
        });

        return response()->json([
            'message' => "Reset {$reset} player(s).",
            'data' => [
                'reset' => $reset,
                'skipped' => $skipped,
            ],
        ]);
    }

    /**
     * Apply the full reset to a single player: stats back to defaults and
     * rating history cleared.
     */
    private function applyReset(Player $player): void
    {
        $player->update([
            'rating' => config('courtly.rating.default_rating', 0.00),
            'rating_status' => 'PROVISIONAL',
            'rating_confidence' => config('courtly.rating.initial_confidence', 0.10),
            'rated_games_count' => 0,
            'total_games' => 0,
            'wins' => 0,
            'losses' => 0,
            'consecutive_wins' => 0,
        ]);

        \App\Models\RatingHistory::where('player_id', $player->id)->delete();
    }

    /**
     * Delete a player permanently from the system — removes them from all
     * sessions, matches, and the player record itself so they can never be
     * added to any future session.
     */
    public function destroy(Player $player): JsonResponse
    {
        $this->authorizePlayer($player);

        if ($player->isInActiveMatch()) {
            return response()->json([
                'message' => 'This player is currently on court and cannot be deleted.',
            ], 409);
        }

        $name = $player->name;

        \Illuminate\Support\Facades\DB::transaction(function () use ($player) {
            // Remove from all sessions
            \App\Models\SessionPlayer::where('player_id', $player->id)->delete();
            // Delete match participation records
            \App\Models\MatchPlayer::where('player_id', $player->id)->delete();
            // Delete rating history
            \App\Models\RatingHistory::where('player_id', $player->id)->delete();
            // Nuke the player
            $player->delete();
        });

        return response()->json([
            'message' => "Player '${name}' deleted permanently.",
        ]);
    }
}
