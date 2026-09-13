<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Bootstrap the platform super admin as part of the migration run so it
     * is created on every deploy (migrate --force) regardless of whether a
     * seeder is ever invoked. Idempotent: safe to run repeatedly.
     */
    public function up(): void
    {
        // Never seed data into the test database — RefreshDatabase runs every
        // migration, and tests create their own super admin via the seeder.
        if (app()->environment('testing')) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'admin@regno.ai'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Lynda197&'),
                'role' => UserRole::SUPER_ADMIN->value,
                'email_verified_at' => now(),
            ]
        );

        // Keep the role authoritative without touching the password, so a
        // changed password survives subsequent deploys.
        $user->forceFill([
            'role' => UserRole::SUPER_ADMIN->value,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $circle = Circle::firstOrCreate(
            ['admin_id' => $user->id],
            [
                'name' => 'Admin',
                'invite_code' => Circle::generateInviteCode(),
            ]
        );

        CircleMember::firstOrCreate([
            'circle_id' => $circle->id,
            'user_id' => $user->id,
        ]);

        Player::firstOrCreate(
            [
                'circle_id' => $circle->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Super Admin',
                'email' => 'admin@regno.ai',
                'rating' => config('courtly.rating.default_rating', 0.00),
                'rating_status' => 'PROVISIONAL',
                'rating_confidence' => config('courtly.rating.initial_confidence', 0.10),
            ]
        );
    }

    public function down(): void
    {
        User::where('email', 'admin@regno.ai')->delete();
    }
};
