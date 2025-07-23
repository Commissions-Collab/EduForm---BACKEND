<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => fake()->optional(0.8)->dateTime(),
            'password' => Hash::make('password'),
            'role' => fake()->randomElement(['teacher', 'student']),
            'otp' => fake()->optional(0.1)->numberBetween(100000, 999999),
            'is_verified' => fake()->boolean(85),
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
            'email' => 'admin@school.com',
            'is_verified' => true,
        ]);
    }

    public function teacher()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'teacher',
        ]);
    }

    public function student()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'student',
        ]);
    }
}
