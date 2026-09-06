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
        private readonly AIProviderInterface $provider,
        private readonly AIRunLogger $logger,
    ) {}

    /**
     * Explain a match selection. Uses AI if enabled, falls back to deterministic.
     */
    public function explain(GameMatch $match): string
    {
        $match->load(['matchPlayers.player', 'matchmakingLog']);

        $players = $match->matchPlayers->map(fn ($mp) => $mp->player)->all();

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

        $input = $this->buildInput($match);

        try {
            $started = microtime(true);
            $result = $this->provider->generateStructuredResponse(
                'You are the matchmaking explainer for Courtly, a social badminton app. '
                .'Given a match that was just created, explain in one or two friendly, '
                .'plain-English sentences WHY these four players were grouped together and '
                .'why the teams were split this way. Mention fairness, ratings, and how long '
                .'players waited where relevant. Keep it short and conversational — no jargon.',
                $input,
                [
                    'type' => 'object',
                    'required' => ['explanation'],
                    'properties' => ['explanation' => ['type' => 'string']],
                ],
            );
            $latencyMs = (int) ((microtime(true) - $started) * 1000);

            $this->logger->log(
                'match_explanation',
                $input,
                $result,
                sessionId: $match->session_id,
                matchId: $match->id,
                status: 'SUCCESS',
                latencyMs: $latencyMs,
            );

            $explanation = trim((string) ($result['explanation'] ?? ''));
            if ($explanation === '') {
                return $deterministic;
            }

            return $explanation;
        } catch (\Throwable $e) {
            Log::warning('ai.explanation.failed', [
                'match_id' => $match->id,
                'error' => $e->getMessage(),
            ]);

            $this->logger->log(
                'match_explanation',
                $input,
                [],
                sessionId: $match->session_id,
                matchId: $match->id,
                status: 'ERROR',
                errorMessage: $e->getMessage(),
            );

            return $deterministic;
        }
    }

    private function buildInput(GameMatch $match): array
    {
        $players = $match->matchPlayers
            ->map(fn ($mp) => [
                'name' => $mp->player->name,
                'rating' => (float) $mp->player->rating,
                'team' => (int) $mp->team,
                'consecutive_wins' => (int) $mp->player->consecutive_wins,
            ])
            ->values()
            ->all();

        $log = $match->matchmakingLog;

        return [
            'match' => [
                'game_number' => (int) $match->game_number,
                'algorithm_version' => (string) $match->algorithm_version,
                'skill_spread' => (float) $match->skill_spread,
                'team_balance_difference' => (float) $match->team_balance_difference,
                'match_quality' => (int) $match->match_quality,
                'team_1_rating' => (float) $match->team_1_rating,
                'team_2_rating' => (float) $match->team_2_rating,
            ],
            'players' => $players,
            'matchmaking_costs' => $log ? [
                'rotation_score' => (float) $log->rotation_score,
                'group_cost' => (float) $log->group_cost,
                'pairing_cost' => (float) $log->pairing_cost,
                'total_cost' => (float) $log->total_cost,
            ] : null,
        ];
    }
}
