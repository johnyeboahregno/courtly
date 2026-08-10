<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players');
            $table->unsignedTinyInteger('team');
            $table->unsignedTinyInteger('position')->nullable();
            $table->decimal('rating_before', 5, 2);
            $table->decimal('rating_after', 5, 2)->nullable();
            $table->decimal('rating_confidence_before', 3, 2);
            $table->decimal('rating_confidence_after', 3, 2)->nullable();
            $table->string('result')->nullable();

            $table->index('match_id');
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
