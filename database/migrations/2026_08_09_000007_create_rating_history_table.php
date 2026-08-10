<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players');
            $table->foreignId('match_id')->constrained('matches');
            $table->decimal('rating_before', 5, 2);
            $table->decimal('rating_after', 5, 2);
            $table->decimal('rating_change', 5, 2);
            $table->decimal('expected_result', 5, 4);
            $table->decimal('actual_result', 3, 2);
            $table->unsignedInteger('k_factor');
            $table->timestamp('created_at')->useCurrent();

            $table->index('player_id');
            $table->index('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_history');
    }
};
