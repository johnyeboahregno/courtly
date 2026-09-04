<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Optional point score, mainly used for tournament standings tiebreaks.
            $table->unsignedSmallInteger('team_1_score')->nullable()->after('winning_team');
            $table->unsignedSmallInteger('team_2_score')->nullable()->after('team_1_score');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['team_1_score', 'team_2_score']);
        });
    }
};
