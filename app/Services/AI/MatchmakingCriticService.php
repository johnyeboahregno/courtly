<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\MatchStatus;
use App\Models\MatchFeedback;
use App\Models\MatchmakingLog;
use App\Models\Session;
use Illuminate\Support\Facades\Log;

/**
 * Analyses matchmaking quality for a session and suggests weight tweaks.
 *
 * Read-only: it never mutates configuration. Suggestions are surfaced to the
 * organiser so they can decide whether to adjust anything.
 */
class MatchmakingCriticService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly AIRunLogger $logger,
    ) {}

    /**
     * @return array{summary: string, issues: array<int, array<string, string>>, suggested_weights: array<string, float>, source: string}
     */
    public function analyze(Session $session): array
    {
        $metrics = $this->buildMetrics($session);

        if (! config('courtly.ai.enabled')) {
            return $this->deterministic($metrics);
        }

        $input = [
            'session' => [
                'name' => $session->name,
                'matchmaking_mode' => (string) $session->matchmaking_mode,
                'number_of_courts' => (int) $session->number_of_courts,
            ],
            'metrics' => $metrics,
            'current_weights' => config('courtly.matchmaking'),
        ];

        try {
            $started = microtime(true);
            $result = $this->provider->generateStructuredResponse(
                'You are the matchmaking quality analyst for Courtly, a social badminton app. '
                .'Given aggregate metrics for a session and the current matchmaking weights, '
                .'write a short plain-English summary of how matchmaking is performing, list up '
                .'to 5 concrete issues with suggestions, and propose weight changes only for the '
                .'keys provided in current_weights. Ground everything in the data — do not invent '
                .'numbers or matches. Use snake_case keys.',
                $input,
                [
                    'type' => 'object',
                    'required' => ['summary', 'issues', 'suggested_weights'],
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'issues' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'suggested_weights' => ['type' => 'object'],
                    ],
                ],
            );
            $latencyMs = (int) ((microtime(true) - $started) * 1000);

            $this->logger->log(
                'matchmaking_critic',
                $input,
                $result,
                sessionId: $session->id,
                status: 'SUCCESS',
                latencyMs: $latencyMs,
            );

            $summary = trim((string) ($result['summary'] ?? ''));
            if ($summary === '') {
                return $this->deterministic($metrics);
            }

            return [
                'summary' => $summary,
                'issues' => $this->issuesList($result['issues'] ?? []),
                'suggested_weights' => $this->weightsMap($result['suggested_weights'] ?? []),
                'source' => 'ai',
            ];
        } catch (\Throwable $e) {
            Log::warning('ai.matchmaking_critic.failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            $this->logger->log(
                'matchmaking_critic',
                $input,
                [],
                sessionId: $session->id,
                status: 'ERROR',
                errorMessage: $e->getMessage(),
            );

            return $this->deterministic($metrics);
        }
    }

    private function buildMetrics(Session $session): array
    {
        $matches = $session->matches()
            ->where('status', MatchStatus::COMPLETED->value)
            ->get();

        $logsCount = MatchmakingLog::query()
            ->where('session_id', $session->id)
            ->count();

        $feedbackCounts = MatchFeedback::query()
            ->whereIn('match_id', $matches->pluck('id'))
            ->selectRaw('quality_rating, COUNT(*) as cnt')
            ->groupBy('quality_rating')
            ->pluck('cnt', 'quality_rating')
            ->all();

        $spreads = $matches->map(fn ($m) => (float) $m->skill_spread)->filter(fn ($v) => $v !== null)->values();
        $balances = $matches->map(fn ($m) => (float) $m->team_balance_difference)->filter(fn ($v) => $v !== null)->values();
        $qualities = $matches->map(fn ($m) => (int) $m->match_quality)->filter(fn ($v) => $v !== null)->values();

        return [
            'total_completed_matches' => $matches->count(),
            'logs_count' => $logsCount,
            'feedback_count' => array_sum($feedbackCounts),
            'feedback_breakdown' => [
                'POOR' => (int) ($feedbackCounts['POOR'] ?? 0),
                'GOOD' => (int) ($feedbackCounts['GOOD'] ?? 0),
                'GREAT' => (int) ($feedbackCounts['GREAT'] ?? 0),
            ],
            'avg_skill_spread' => $spreads->isNotEmpty() ? round($spreads->avg(), 2) : null,
            'avg_team_balance_difference' => $balances->isNotEmpty() ? round($balances->avg(), 2) : null,
            'avg_match_quality' => $qualities->isNotEmpty() ? (int) round($qualities->avg()) : null,
        ];
    }

    private function deterministic(array $metrics): array
    {
        if ($metrics['total_completed_matches'] < 1) {
            return [
                'summary' => 'Not enough completed matches yet to analyse matchmaking quality — play a few games first.',
                'issues' => [],
                'suggested_weights' => [],
                'source' => 'deterministic',
            ];
        }

        $issues = [];
        $suggested = [];

        if (($metrics['avg_team_balance_difference'] ?? null) !== null && $metrics['avg_team_balance_difference'] > 10) {
            $issues[] = [
                'issue' => 'Teams are frequently uneven.',
                'severity' => 'medium',
                'suggestion' => 'Increase balance_weight so lopsided teams are penalised more.',
            ];
            $suggested['balance_weight'] = (float) ((int) config('courtly.matchmaking.balance_weight', 15) + 5);
        }

        if (($metrics['avg_skill_spread'] ?? null) !== null && $metrics['avg_skill_spread'] > 20) {
            $issues[] = [
                'issue' => 'Skill spread across the four players is often wide.',
                'severity' => 'low',
                'suggestion' => 'Increase skill_spread_weight to group similarly rated players together.',
            ];
            $suggested['skill_spread_weight'] = (float) ((int) config('courtly.matchmaking.skill_spread_weight', 8) + 2);
        }

        if (($metrics['feedback_breakdown']['POOR'] ?? 0) > 0) {
            $issues[] = [
                'issue' => sprintf('%d match(es) were rated poor quality.', $metrics['feedback_breakdown']['POOR']),
                'severity' => 'high',
                'suggestion' => 'Review the recent matches and their matchmaking logs.',
            ];
        }

        $summary = sprintf(
            '%d matches played. Average skill spread %.1f, average team difference %.1f, average match quality %s.',
            $metrics['total_completed_matches'],
            (float) ($metrics['avg_skill_spread'] ?? 0),
            (float) ($metrics['avg_team_balance_difference'] ?? 0),
            $metrics['avg_match_quality'] !== null ? $metrics['avg_match_quality'].'/100' : 'n/a',
        );

        return [
            'summary' => $summary,
            'issues' => $issues,
            'suggested_weights' => $suggested,
            'source' => 'deterministic',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function issuesList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $issue = trim((string) ($item['issue'] ?? ''));
            if ($issue === '') {
                continue;
            }

            $out[] = [
                'issue' => $issue,
                'severity' => trim((string) ($item['severity'] ?? 'info')),
                'suggestion' => trim((string) ($item['suggestion'] ?? '')),
            ];
        }

        return array_slice($out, 0, 6);
    }

    /**
     * Keep only known matchmaking keys with numeric values.
     *
     * @return array<string, float>
     */
    private function weightsMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $allowed = array_keys(config('courtly.matchmaking'));
        $out = [];
        foreach ($value as $key => $val) {
            if (in_array($key, $allowed, true) && (is_int($val) || is_float($val))) {
                $out[(string) $key] = (float) $val;
            }
        }

        return $out;
    }
}
