<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;

use App\Models\Player;
use Illuminate\Http\JsonResponse;

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
     * Delete a player permanently from the system — removes them from all
     * sessions, matches, and the player record itself so they can never be
     * added to any future session.
     */
    public function destroy(Player $player): JsonResponse
    {
        $this->authorizePlayer($player);

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
