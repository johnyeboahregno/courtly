<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old index since we're using ID-based cursor, not timestamp
        Schema::table('realtime_events', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'created_at']);
        });

        // Add new index optimized for ID-based pagination
        Schema::table('realtime_events', function (Blueprint $table) {
            $table->index(['session_id', 'id']);
            // Index for cleanup queries (prune old events)
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('realtime_events', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('realtime_events', function (Blueprint $table) {
            $table->index(['session_id', 'created_at']);
        });
    }
};
