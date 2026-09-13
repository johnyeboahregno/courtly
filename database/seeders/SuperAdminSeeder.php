<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Services\CircleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the platform super admin account. Idempotent and safe to run on
 * every deploy: the account (and its personal circle) are created on the
 * first run, and later runs only ensure the role/verification are correct.
 * The password is set ONLY on creation so a super admin's self-service
 * password change is never overwritten by a subsequent deploy.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'admin@regno.ai');
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'Lynda197&');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'role' => UserRole::SUPER_ADMIN->value,
                'email_verified_at' => now(),
            ]
        );

        // Keep the role authoritative without touching the password, so a
        // changed password survives redeploys.
        $user->forceFill([
            'role' => UserRole::SUPER_ADMIN->value,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->ensurePersonalCircle($user);

        $this->command?->info('Super admin ready: '.$email);
    }

    /**
     * A super admin is a normal account underneath — it needs a personal
     * circle (and a self player) so the rest of the app behaves identically
     * to a freshly registered user.
     */
    private function ensurePersonalCircle(User $user): void
    {
        if ($user->personalCircle) {
            return;
        }

        $service = app(CircleService::class);
        $circle = $service->createPersonalCircle($user, 'Admin');
        $service->ensureLinkedPlayer($user, $circle);
    }
}
