<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Player;
use App\Services\PlayerAnalyticsService;
use Illuminate\Support\Facades\Log;

/**
 * Generates a natural-language coaching summary for a player.
 *
 * Uses AI when enabled and configured; always falls back to a deterministic
 * summary built from the same analytics payload the stats screen already uses.
 */
class PlayerCoachService
{
    public function __construct(
        private readonly PlayerAnalyticsService $analytics,
        private readonly AIProviderInterface $provider,
        private readonly AIRunLogger $logger,
    ) {}

    /**
     * @return array{narrative: string, strengths: array<int, string>, improvements: array<int, string>, tips: array<int, string>, source: string}
     */
    public function coach(Player $player): array
    {
        $stats = $this->analytics->build($player);

        if (! config('courtly.ai.enabled')) {
            return $this->deterministic($player, $stats);
        }

        $input = [
            'player' => [
                'name' => $player->name,
                'gender' => $player->gender?->value,
                'rating' => (float) $player->rating,
            ],
            'summary' => $stats['summary'],
            'form' => $stats['form'],
        ];

        try {
            $started = microtime(true);
            $result = $this->provider->generateStructuredResponse(
                'You are the player coach for Courtly, a social badminton app. '
                .'Given a player\'s statistics, write a short, encouraging coaching report: '
                .'a 1-2 sentence narrative, then 2-4 strengths, 2-4 areas to improve, and '
                .'2-4 practical tips. Be friendly, specific, and grounded only in the data '
                .'provided. Never invent matches or numbers.',
                $input,
                [
                    'type' => 'object',
                    'required' => ['narrative', 'strengths', 'improvements', 'tips'],
                    'properties' => [
                        'narrative' => ['type' => 'string'],
                        'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'improvements' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'tips' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            );
            $latencyMs = (int) ((microtime(true) - $started) * 1000);

            $this->logger->log(
                'player_coach',
                $input,
                $result,
                status: 'SUCCESS',
                latencyMs: $latencyMs,
            );

            $narrative = trim((string) ($result['narrative'] ?? ''));
            if ($narrative === '') {
                return $this->deterministic($player, $stats);
            }

            return [
                'narrative' => $narrative,
                'strengths' => $this->stringList($result['strengths'] ?? []),
                'improvements' => $this->stringList($result['improvements'] ?? []),
                'tips' => $this->stringList($result['tips'] ?? []),
                'source' => 'ai',
            ];
        } catch (\Throwable $e) {
            Log::warning('ai.player_coach.failed', [
                'player_id' => $player->id,
                'error' => $e->getMessage(),
            ]);

            $this->logger->log(
                'player_coach',
                $input,
                [],
                status: 'ERROR',
                errorMessage: $e->getMessage(),
            );

            return $this->deterministic($player, $stats);
        }
    }

    private function deterministic(Player $player, array $stats): array
    {
        $s = $stats['summary'];
        $name = $player->name;

        $narrative = sprintf(
            '%s is rated %.0f (%s) with a %dW–%dL record across %d games.',
            $name,
            (float) $s['rating'],
            strtolower((string) $s['rating_status']),
            (int) $s['wins'],
            (int) $s['losses'],
            (int) $s['total_games'],
        );

        if (($s['current_streak']['type'] ?? null) !== null && (int) $s['current_streak']['length'] > 0) {
            $narrative .= sprintf(
                ' Currently on a %d-game %s streak.',
                (int) $s['current_streak']['length'],
                strtolower((string) $s['current_streak']['type']),
            );
        }

        $strengths = [];
        if ((float) $s['win_percentage'] >= 50) {
            $strengths[] = sprintf('Wins %.0f%% of games.', (float) $s['win_percentage']);
        }
        if ((int) $s['longest_win_streak'] >= 3) {
            $strengths[] = sprintf('Longest win streak of %d games.', (int) $s['longest_win_streak']);
        }
        if (! empty($s['most_common_teammate'])) {
            $t = $s['most_common_teammate'];
            $strengths[] = sprintf('Works well with %s (%d games, %d wins).', $t['name'], (int) $t['games'], (int) $t['wins']);
        }
        if (($s['upset_rate'] ?? null) !== null && (float) $s['upset_rate'] >= 30) {
            $strengths[] = sprintf('Pulls off upsets in %.0f%% of underdog games.', (float) $s['upset_rate']);
        }

        $improvements = [];
        if ((int) $s['longest_loss_streak'] >= 3) {
            $improvements[] = sprintf('Longest losing streak of %d games.', (int) $s['longest_loss_streak']);
        }
        if (! empty($s['toughest_opponent'])) {
            $o = $s['toughest_opponent'];
            $improvements[] = sprintf('Struggles against %s (%d losses in %d meetings).', $o['name'], (int) $o['losses'], (int) $o['games']);
        }
        if ((float) $s['rating_momentum'] < 0) {
            $improvements[] = sprintf('Rating trending down (%.2f per game recently).', (float) $s['rating_momentum']);
        }

        $tips = [
            'Keep rallies long and move your opponents side to side.',
            'Rotate with your partner and call the serve early.',
        ];
        if (! empty($s['toughest_opponent'])) {
            $tips[] = sprintf('Against %s, pressure the weaker receiver on serve.', $s['toughest_opponent']['name']);
        }

        if ($strengths === []) {
            $strengths[] = 'Building match experience — every game counts.';
        }
        if ($improvements === []) {
            $improvements[] = 'No obvious weaknesses in the recent data yet.';
        }

        return [
            'narrative' => $narrative,
            'strengths' => array_slice($strengths, 0, 4),
            'improvements' => array_slice($improvements, 0, 4),
            'tips' => array_slice($tips, 0, 4),
            'source' => 'deterministic',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item): string => trim((string) $item),
            $value
        ), fn (string $item): bool => $item !== ''));
    }
}
