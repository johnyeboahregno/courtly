<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CircleFactory extends Factory
{
    protected $model = Circle::class;

    public function definition(): array
    {
        return [
            'name' => fn () => Circle::uniqueName(fake()->company().' Circle'),
            'admin_id' => User::factory(),
            'invite_code' => fn () => Circle::generateInviteCode(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Circle $circle) {
            CircleMember::create([
                'circle_id' => $circle->id,
                'user_id' => $circle->admin_id,
            ]);
        });
    }
}
