<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;

use App\Models\GameMatch;
use App\Services\MatchResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

        $result = $this->resultService->recordResult(
            $match,
            $validated['winning_team'],
            (bool) ($validated['close_game'] ?? false)
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
        ]);

        $result = $this->resultService->correctResult($match, $validated['winning_team']);

        return response()->json(['data' => $result]);
    }
}
