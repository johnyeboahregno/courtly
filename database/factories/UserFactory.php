<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'PLAYER',
        ];
    }

    /**
     * Every factory user owns a personal circle (and is a member of it).
     * The user's self player is deliberately NOT created here so tests that
     * expect an empty roster still pass.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $circle = Circle::create([
                'name' => Circle::uniqueName($user->name."'s Circle"),
                'admin_id' => $user->id,
                'invite_code' => Circle::generateInviteCode(),
            ]);

            CircleMember::create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
            ]);
        });
    }
}
