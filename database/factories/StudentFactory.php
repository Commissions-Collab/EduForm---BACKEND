<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'lrn' => $this->faker->unique()->numerify('############'),
            'student_id' => 'STU-' . $this->faker->unique()->numberBetween(1000, 9999),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional()->firstName(),
            'last_name' => $this->faker->lastName(),
            'birthday' => $this->faker->date('Y-m-d', '-12 years'),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'address' => $this->faker->optional()->address(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'parent_guardian_name' => $this->faker->name(),
            'relationship_to_student' => $this->faker->randomElement(['Mother', 'Father', 'Guardian', 'Grandmother', 'Grandfather']),
            'parent_guardian_phone' => $this->faker->phoneNumber(),
            'parent_guardian_email' => $this->faker->optional()->safeEmail(),
            'photo' => null,
        ];
    }
}
