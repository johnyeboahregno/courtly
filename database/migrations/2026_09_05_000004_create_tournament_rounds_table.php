<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->onDelete('cascade');
            $table->unsignedInteger('round_number');
            $table->string('status')->default('PENDING');
            $table->timestamps();

            $table->unique(['session_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_rounds');
    }
};
