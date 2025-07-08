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
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'LRN' => $this->faker->unique()->numerify('############'), // 12 digits
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional()->firstName(),
            'last_name' => $this->faker->lastName(),
            'birthday' => $this->faker->date(),
            'gender' => 'male', // adjust if you use enum
            'email' => $this->faker->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'parents_fullname' => $this->faker->name(),
            'relationship_to_student' => $this->faker->randomElement(['Father', 'Mother', 'Guardian']),
            'parents_number' => $this->faker->numerify('09#########'),
            'parents_email' => $this->faker->safeEmail(),
            'role' => 'student',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'id' => 1,
            'role' => 'super_admin',
            'LRN' => 000000000001,
            'parents_fullname' => null,
            'relationship_to_student' => null,
            'parents_number' => null,
            'parents_email' => null,
        ]);
    }

    public function teacher(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'teacher',
            'LRN' => $this->faker->unique()->numerify('############'),
            'parents_fullname' => null,
            'relationship_to_student' => null,
            'parents_number' => null,
            'parents_email' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
