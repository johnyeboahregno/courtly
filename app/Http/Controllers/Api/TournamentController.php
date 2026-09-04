<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;

use App\Exceptions\TournamentSetupException;
use App\Models\Session;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly TournamentService $tournament,
    ) {}

    /**
     * Current teams for this tournament session, auto-generating a first
     * pass if none exist yet (organiser hasn't previewed/started).
     */
    public function teams(Session $session): JsonResponse
    {
        $this->authorizeSession($session);
        $this->assertTournament($session);

        try {
            $teams = $this->tournament->currentOrPreviewTeams($session);
        } catch (TournamentSetupException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->tournament->formatTeams($teams)]);
    }

    /**
     * Re-shuffle the teams from scratch (only before the session starts).
     */
    public function regenerate(Session $session): JsonResponse
    {
        $this->authorizeSession($session);
        $this->assertTournament($session);

        try {
            $teams = $this->tournament->regenerateTeams($session);
        } catch (TournamentSetupException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->tournament->formatTeams($teams)]);
    }

    /**
     * Swap two players between their current teams (manual reshuffle before start).
     */
    public function swap(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($session);
        $this->assertTournament($session);

        $validated = $request->validate([
            'player_id_a' => ['required', 'integer'],
            'player_id_b' => ['required', 'integer', 'different:player_id_a'],
        ]);

        try {
            $this->tournament->swapPlayers($session, $validated['player_id_a'], $validated['player_id_b']);
        } catch (TournamentSetupException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $teams = $this->tournament->currentOrPreviewTeams($session);

        return response()->json(['data' => $this->tournament->formatTeams($teams)]);
    }

    private function assertTournament(Session $session): void
    {
        abort_unless($session->isTournament(), 422, 'This session is not in tournament mode.');
    }
}
