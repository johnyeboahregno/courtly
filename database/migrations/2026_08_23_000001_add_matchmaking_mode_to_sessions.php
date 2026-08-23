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
            // Matchmaking strategy for this session:
            //   'smart' — weighted/refactored matchmaking (default)
            //   'peg'   — traditional peg-board queue
            $table->string('matchmaking_mode', 16)->default('smart')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('matchmaking_mode');
        });
    }
};
