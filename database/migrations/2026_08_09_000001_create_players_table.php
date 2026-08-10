<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->decimal('rating', 5, 2)->default(50.00);
            $table->string('rating_status')->default('PROVISIONAL');
            $table->decimal('rating_confidence', 3, 2)->default(0.10);
            $table->unsignedInteger('rated_games_count')->default(0);
            $table->unsignedInteger('total_games')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
