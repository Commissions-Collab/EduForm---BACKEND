<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'employee_id' => 'T' . str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.7)->lastName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'hire_date' => fake()->dateTimeBetween('-10 years', '-6 months'),
            'employment_status' => fake()->randomElement(['active', 'active', 'active', 'active', 'inactive']),
        ];
    }

}
