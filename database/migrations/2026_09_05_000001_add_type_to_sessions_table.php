<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            // 'casual' — normal free-for-all matchmaking (default)
            // 'tournament' — round-robin ladder with auto-paired teams
            $table->string('type', 16)->default('casual')->after('matchmaking_mode');
            $table->timestamp('tournament_finished_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['type', 'tournament_finished_at']);
        });
    }
};
