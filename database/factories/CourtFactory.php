<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CourtStatus;
use App\Models\Court;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'court_number' => fake()->numberBetween(1, 8),
            'status' => CourtStatus::AVAILABLE->value,
        ];
    }

    public function playing(): static
    {
        return $this->state(fn () => [
            'status' => CourtStatus::PLAYING->value,
        ]);
    }
}