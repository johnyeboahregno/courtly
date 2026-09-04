<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_team_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_team_id')->constrained('tournament_teams')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players');
            // Denormalized so a player can be uniquely constrained to one team per session.
            $table->foreignId('session_id')->constrained('sessions')->onDelete('cascade');

            $table->unique(['session_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_team_players');
    }
};
