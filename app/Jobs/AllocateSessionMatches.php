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

        $matches = $matchmaking->allocateMatches($session);

        if ($matches !== []) {
            $events->publish($session->id, 'session.updated', [
                'session_id' => $session->id,
                'matches_created' => count($matches),
            ]);
        }
    }
}
