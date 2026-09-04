<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Session;
use App\Services\MatchmakingService;
use App\Services\RealtimeEventService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AllocateSessionMatches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $sessionId)
    {
    }

    public function handle(
        MatchmakingService $matchmaking,
        RealtimeEventService $events,
    ): void {
        $session = Session::find($this->sessionId);

        if (! $session) {
            return;
        }

        $matches = [];
        $maxPasses = max(1, (int) $session->number_of_courts);

        // Keep allocating until the server has either filled every possible
        // court or there are fewer than four eligible waiting players left.
        // Repeating is intentional: a strategy may return a partial set when
        // its first candidate pass cannot find enough non-overlapping groups.
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $created = $matchmaking->allocateMatches($session->fresh());
            if ($created === []) {
                break;
            }

            $matches = array_merge($matches, $created);
        }

        if ($matches !== []) {
            $events->publish($session->id, 'session.updated', [
                'session_id' => $session->id,
                'matches_created' => count($matches),
            ]);
        }
    }
}
