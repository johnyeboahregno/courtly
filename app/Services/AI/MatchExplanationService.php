<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\GameMatch;
use App\Services\MatchmakingService;
use Illuminate\Support\Facades\Log;

class MatchExplanationService
{
    public function __construct(
        private readonly MatchmakingService $matchmaking,
    ) {}

    /**
     * Explain a match selection. Uses AI if enabled, falls back to deterministic.
     */
    public function explain(GameMatch $match): string
    {
        $match->load('matchPlayers.player');

        $players = $match->matchPlayers->map(fn ($mp) => $mp->player)->toArray();

        // Always generate deterministic explanation first
        $deterministic = $this->matchmaking->generateExplanation(
            $players,
            (float) $match->skill_spread,
            (float) $match->team_balance_difference,
            (int) $match->match_quality,
        );

        // If AI is not enabled, return deterministic explanation
        if (! config('courtly.ai.enabled')) {
            return $deterministic;
        }

        try {
            // AI enhancement would go here — for now, return deterministic
            return $deterministic;
        } catch (\Throwable $e) {
            Log::warning('ai.explanation.failed', [
                'match_id' => $match->id,
                'error' => $e->getMessage(),
            ]);

            return $deterministic;
        }
    }
}
