<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->student()->create();

        return [
            'user_id' => $user->id,
            'student_id' => date('Y') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'student_number' => 'STU-' . $this->faker->unique()->numberBetween(10000, 99999),
            'total_balance' => $this->faker->randomFloat(2, 0, 50000),
            'enrollment_status' => $this->faker->randomElement(['pending', 'active', 'suspended', 'graduated']),
            'enrollment_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'metadata' => null,
        ];
    }
}