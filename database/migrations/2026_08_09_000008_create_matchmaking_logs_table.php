<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchmaking_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->onDelete('cascade');
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->string('algorithm_version');
            $table->unsignedInteger('candidate_pool_size')->default(0);
            $table->decimal('rotation_score', 5, 2)->default(0);
            $table->decimal('skill_spread', 5, 2)->default(0);
            $table->decimal('team_balance_difference', 5, 2)->default(0);
            $table->decimal('repeat_teammate_penalty', 5, 2)->default(0);
            $table->decimal('recent_teammate_penalty', 5, 2)->default(0);
            $table->decimal('opponent_penalty', 5, 2)->default(0);
            $table->decimal('winner_priority_score', 5, 2)->default(0);
            $table->decimal('group_cost', 5, 2)->default(0);
            $table->decimal('pairing_cost', 5, 2)->default(0);
            $table->decimal('total_cost', 5, 2)->default(0);
            $table->unsignedInteger('calculation_time_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matchmaking_logs');
    }
};
