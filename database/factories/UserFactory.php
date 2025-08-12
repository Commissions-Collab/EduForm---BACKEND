<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // Default password
            'role' => $this->faker->randomElement(['super_admin', 'teacher', 'student']),
            'otp' => null,
            'is_verified' => 1,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'super_admin',
        ]);
    }

    public function teacher(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'teacher',
            'password' => 'password'
        ]);
    }

    public function student(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'student',
            'password' => 'password'
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
            'is_verified' => 0,
        ]);
    }
}
