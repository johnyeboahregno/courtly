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
            // 'round_robin' — every team plays every other team once (default)
            // 'ladder' — teams are ranked; a team can challenge the one directly above it
            $table->string('tournament_format', 20)->default('round_robin')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('tournament_format');
        });
    }
};
