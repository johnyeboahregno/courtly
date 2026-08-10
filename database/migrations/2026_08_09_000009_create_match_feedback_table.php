<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('player_id')->nullable()->constrained('players')->onDelete('set null');
            $table->string('quality_rating');
            $table->timestamp('created_at')->useCurrent();

            $table->index('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_feedback');
    }
};
