<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sport' => 'badminton',
            'date' => fake()->date(),
            'number_of_courts' => fake()->numberBetween(1, 4),
            'status' => SessionStatus::UPCOMING->value,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SessionStatus::ACTIVE->value,
            'started_at' => now(),
        ]);
    }

    /**
     * Resolve the session's circle from its creator.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Session $session) {
            if ($session->circle_id !== null) {
                return;
            }

            try {
                $user = $session->created_by instanceof User
                    ? $session->created_by
                    : User::find($session->created_by);

                $session->circle_id = $user?->personalCircle?->id;
            } catch (\Throwable) {
                // No database available (e.g. Unit tests using make()).
            }
        });
    }

    public function withCourts(int $count): static
    {
        return $this->afterCreating(function (Session $session) use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                \App\Models\Court::create([
                    'session_id' => $session->id,
                    'court_number' => $i,
                    'status' => 'AVAILABLE',
                ]);
            }
        });
    }
}
