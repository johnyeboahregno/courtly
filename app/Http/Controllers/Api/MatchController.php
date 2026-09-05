<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;

use App\Models\GameMatch;
use App\Services\MatchResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MatchController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly MatchResultService $resultService,
    ) {}

    /**
     * Record a match result.
     */
    public function recordResult(Request $request, GameMatch $match): JsonResponse
    {
        $this->authorizeSession($match->session);

        $validated = $request->validate([
            'winning_team' => ['required', 'integer', 'in:1,2'],
            'close_game' => ['sometimes', 'boolean'],
            'team_1_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99'],
            'team_2_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $team1Score = $validated['team_1_score'] ?? null;
        $team2Score = $validated['team_2_score'] ?? null;

        $this->assertValidScores($validated['winning_team'], $team1Score, $team2Score);

        $result = $this->resultService->recordResult(
            $match,
            $validated['winning_team'],
            (bool) ($validated['close_game'] ?? false),
            $team1Score,
            $team2Score,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Correct a completed match's winner (recalculates ratings/stats).
     */
    public function correctResult(Request $request, GameMatch $match): JsonResponse
    {
        $this->authorizeSession($match->session);

        $validated = $request->validate([
            'winning_team' => ['required', 'integer', 'in:1,2'],
            'team_1_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99'],
            'team_2_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $team1Score = $validated['team_1_score'] ?? null;
        $team2Score = $validated['team_2_score'] ?? null;

        $this->assertValidScores($validated['winning_team'], $team1Score, $team2Score);

        $result = $this->resultService->correctResult(
            $match,
            $validated['winning_team'],
            $team1Score,
            $team2Score,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Scores are optional, but when supplied they must be a legal badminton
     * result: play to the target, win by two, no upper cap.
     *
     * @throws ValidationException
     */
    private function assertValidScores(int $winningTeam, ?int $team1Score, ?int $team2Score): void
    {
        if ($team1Score === null && $team2Score === null) {
            return;
        }

        if ($team1Score === null || $team2Score === null) {
            throw ValidationException::withMessages([
                'team_1_score' => 'Both team scores are required when recording a score.',
            ]);
        }

        $winnerScore = $winningTeam === 1 ? $team1Score : $team2Score;
        $loserScore = $winningTeam === 1 ? $team2Score : $team1Score;

        if ($winnerScore <= $loserScore) {
            throw ValidationException::withMessages([
                'team_1_score' => 'The winning team must have the higher score.',
            ]);
        }

        $target = (int) config('courtly.session.match_points', 21);

        if ($winnerScore < $target) {
            throw ValidationException::withMessages([
                'team_1_score' => "The winning team must reach {$target} points.",
            ]);
        }

        $margin = $winnerScore - $loserScore;

        if ($winnerScore === $target && $margin < 2) {
            throw ValidationException::withMessages([
                'team_1_score' => 'A game must be won by at least two points.',
            ]);
        }

        if ($winnerScore > $target && $margin !== 2) {
            throw ValidationException::withMessages([
                'team_1_score' => "Past {$target} points the game ends as soon as a team leads by two.",
            ]);
        }
    }
}
