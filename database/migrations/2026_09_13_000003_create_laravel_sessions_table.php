<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated Laravel session table.
 *
 * The app already has its own `sessions` table (badminton sessions), so
 * Laravel's session driver must NOT reuse that name. On Laravel Cloud the
 * filesystem is ephemeral, so the `file` session driver loses every session
 * on deploy/restart (which manifests as "419 Page Expired" from stale CSRF
 * tokens). Point SESSION_DRIVER=database at this table instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laravel_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laravel_sessions');
    }
};
