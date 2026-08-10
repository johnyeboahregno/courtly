<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->onDelete('cascade');
            $table->unsignedInteger('court_number');
            $table->string('status')->default('AVAILABLE');

            $table->unique(['session_id', 'court_number']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
