<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('invite_code')->unique()->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['circle_id', 'user_id'], 'circle_members_circle_user_unique');
        });

        // ── Add the new scoping columns (nullable first, backfilled below) ──
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable();
            $table->string('email')->nullable();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable();
        });

        // ── Repurpose players.user_id from "owner" to "linked account". ──
        // It must become nullable BEFORE we null it out for guest players,
        // so drop the FK, change the column, then re-add with SET NULL.
        Schema::table('players', function (Blueprint $table) {
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable) {
                // Already dropped (idempotent re-run).
            }

            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // ── Backfill: one personal circle per existing user ────────────────
        $users = DB::table('users')->orderBy('id')->get();

        foreach ($users as $user) {
            $circleId = DB::table('circles')->insertGetId([
                'name' => $this->uniqueCircleName(trim((string) $user->name)."'s Circle"),
                'admin_id' => $user->id,
                'invite_code' => $this->uniqueInviteCode(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('circle_members')->insert([
                'circle_id' => $circleId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // The user's own profile row is the earliest-created player for
            // that account — keep it linked to the account. Every other named
            // player in the old roster becomes a guest (no account link).
            $selfPlayerId = DB::table('players')->where('user_id', $user->id)->min('id');

            DB::table('players')->where('user_id', $user->id)->update(['circle_id' => $circleId]);

            if ($selfPlayerId !== null) {
                DB::table('players')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $selfPlayerId)
                    ->update(['user_id' => null]);
            }

            DB::table('sessions')->where('created_by', $user->id)->update(['circle_id' => $circleId]);
        }

        // Defensive: any row left without a circle falls back to the first one.
        $firstCircleId = DB::table('circles')->orderBy('id')->value('id');
        if ($firstCircleId !== null) {
            DB::table('players')->whereNull('circle_id')->update(['circle_id' => $firstCircleId]);
            DB::table('sessions')->whereNull('circle_id')->update(['circle_id' => $firstCircleId]);
        }

        // ── players: enforce circle scoping ─────────────────────────────────
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable(false)->change();
            $table->foreign('circle_id')->references('id')->on('circles')->cascadeOnDelete();
            $table->index('circle_id');
            $table->index('email');

            try {
                $table->dropUnique('players_user_name_unique');
            } catch (\Throwable) {
                // Already dropped.
            }

            $table->unique(['circle_id', 'name'], 'players_circle_name_unique');
        });

        // ── sessions: enforce circle scoping ────────────────────────────────
        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable(false)->change();
            $table->foreign('circle_id')->references('id')->on('circles')->cascadeOnDelete();
            $table->index('circle_id');
        });
    }

    private function uniqueCircleName(string $desired): string
    {
        $desired = trim($desired) !== '' ? trim($desired) : 'Circle';
        $name = $desired;
        $suffix = 2;

        while (DB::table('circles')->where('name', $name)->exists()) {
            $name = $desired.' '.$suffix;
            $suffix++;
        }

        return $name;
    }

    private function uniqueInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (DB::table('circles')->where('invite_code', $code)->exists());

        return $code;
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            try {
                $table->dropForeign(['circle_id']);
            } catch (\Throwable) {
            }
            $table->dropColumn('circle_id');
        });

        Schema::table('players', function (Blueprint $table) {
            try {
                $table->dropUnique('players_circle_name_unique');
            } catch (\Throwable) {
            }
            try {
                $table->dropForeign(['circle_id']);
            } catch (\Throwable) {
            }
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable) {
            }

            $table->dropColumn(['circle_id', 'email']);

            // Schema-only restoration — the old ownership data is not rebuilt.
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'name'], 'players_user_name_unique');
        });

        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
    }
};
