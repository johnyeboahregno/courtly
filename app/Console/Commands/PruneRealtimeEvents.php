<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneRealtimeEvents extends Command
{
    protected $signature = 'realtime:prune {--days=7 : Number of days to retain}';

    protected $description = 'Remove realtime events older than the specified number of days. Polling-based architecture only needs recent events.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = DB::table('realtime_events')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} realtime events older than {$days} days.");

        return 0;
    }
}
