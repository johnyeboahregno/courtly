<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────
        // players — every player now belongs to exactly one user, and
        // player names are unique *within* a user rather than globally.
        // ─────────────────────────────────────────────────────────────

        // 1. Drop the global unique name constraint (if still present).
        if (Schema::hasIndex('players', 'players_name_unique')) {
            Schema::table('players', function (Blueprint $table) {
                $table->dropUnique('players_name_unique');
            });
        }

        // 2. Backfill any orphaned players onto the first user (defensive —
        //    the app always creates at least one user on registration).
        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId !== null) {
            DB::table('players')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        // 3. Drop the SET NULL foreign key FIRST — MySQL rejects changing a
        //    column to NOT NULL while an ON DELETE SET NULL FK references it.
        Schema::table('players', function (Blueprint $table) {
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable) {
                // Foreign key already dropped (idempotent re-run).
            }
        });

        // 4. Make user_id required.
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        // 5. Re-add the FK with CASCADE (deleting a user removes their players).
        Schema::table('players', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // 6. Enforce per-user unique names.
        Schema::table('players', function (Blueprint $table) {
            $table->unique(['user_id', 'name'], 'players_user_name_unique');
        });

        // ─────────────────────────────────────────────────────────────
        // sessions — every session must have an owner.
        // ─────────────────────────────────────────────────────────────

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId !== null) {
            DB::table('sessions')->whereNull('created_by')->update(['created_by' => $firstUserId]);
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique('players_user_name_unique');
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unique('name', 'players_name_unique');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }
};
