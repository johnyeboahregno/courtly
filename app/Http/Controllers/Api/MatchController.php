<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\GameMatch;
use App\Services\MatchResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function __construct(
        private readonly MatchResultService $resultService,
    ) {}

    /**
     * Record a match result.
     */
    public function recordResult(Request $request, GameMatch $match): JsonResponse
    {
        // public

        $validated = $request->validate([
            'winning_team' => ['required', 'integer', 'in:1,2'],
        ]);

        $result = $this->resultService->recordResult($match, $validated['winning_team']);

        return response()->json(['data' => $result]);
    }

    /**
     * Correct a completed match's winner (recalculates ratings/stats).
     */
    public function correctResult(Request $request, GameMatch $match): JsonResponse
    {
        // public

        $validated = $request->validate([
            'winning_team' => ['required', 'integer', 'in:1,2'],
        ]);

        $result = $this->resultService->correctResult($match, $validated['winning_team']);

        return response()->json(['data' => $result]);
    }
}
