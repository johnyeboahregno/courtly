<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('visibility', 16)->default('PUBLIC')->after('invite_code');
            $table->string('location_label')->nullable()->after('visibility');
        });

        Schema::create('circle_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('PENDING');
            $table->timestamps();
            $table->unique(['circle_id', 'user_id'], 'circle_join_requests_circle_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_join_requests');

        Schema::table('circles', function (Blueprint $table) {
            $table->dropColumn(['description', 'visibility', 'location_label']);
        });
    }
};
