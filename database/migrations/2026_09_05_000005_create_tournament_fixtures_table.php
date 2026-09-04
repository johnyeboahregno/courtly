<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_round_id')->constrained('tournament_rounds')->onDelete('cascade');
            $table->foreignId('home_team_id')->constrained('tournament_teams');
            // Null away team = a bye for the home team this round.
            $table->foreignId('away_team_id')->nullable()->constrained('tournament_teams');
            $table->foreignId('match_id')->nullable()->constrained('matches');
            $table->string('status')->default('PENDING');
            $table->timestamps();

            $table->index(['tournament_round_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_fixtures');
    }
};
