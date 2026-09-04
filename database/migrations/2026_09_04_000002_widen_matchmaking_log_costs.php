<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchmaking_logs', function (Blueprint $table): void {
            $table->decimal('rotation_score', 10, 2)->default(0)->change();
            $table->decimal('skill_spread', 10, 2)->default(0)->change();
            $table->decimal('team_balance_difference', 10, 2)->default(0)->change();
            $table->decimal('repeat_teammate_penalty', 10, 2)->default(0)->change();
            $table->decimal('recent_teammate_penalty', 10, 2)->default(0)->change();
            $table->decimal('opponent_penalty', 10, 2)->default(0)->change();
            $table->decimal('winner_priority_score', 10, 2)->default(0)->change();
            $table->decimal('group_cost', 10, 2)->default(0)->change();
            $table->decimal('pairing_cost', 10, 2)->default(0)->change();
            $table->decimal('total_cost', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('matchmaking_logs', function (Blueprint $table): void {
            $table->decimal('rotation_score', 5, 2)->default(0)->change();
            $table->decimal('skill_spread', 5, 2)->default(0)->change();
            $table->decimal('team_balance_difference', 5, 2)->default(0)->change();
            $table->decimal('repeat_teammate_penalty', 5, 2)->default(0)->change();
            $table->decimal('recent_teammate_penalty', 5, 2)->default(0)->change();
            $table->decimal('opponent_penalty', 5, 2)->default(0)->change();
            $table->decimal('winner_priority_score', 5, 2)->default(0)->change();
            $table->decimal('group_cost', 5, 2)->default(0)->change();
            $table->decimal('pairing_cost', 5, 2)->default(0)->change();
            $table->decimal('total_cost', 5, 2)->default(0)->change();
        });
    }
};
