<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->onDelete('cascade');
            $table->foreignId('court_id')->constrained('courts');
            $table->unsignedInteger('game_number');
            $table->string('status')->default('CREATED');
            $table->unsignedTinyInteger('winning_team')->nullable();
            $table->decimal('team_1_rating', 5, 2)->nullable();
            $table->decimal('team_2_rating', 5, 2)->nullable();
            $table->decimal('team_balance_difference', 5, 2)->nullable();
            $table->decimal('skill_spread', 5, 2)->nullable();
            $table->unsignedTinyInteger('match_quality')->nullable();
            $table->string('algorithm_version')->default('courtly-v1.0');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
            $table->index(['session_id', 'game_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
