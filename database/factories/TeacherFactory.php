<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        // Create a user with role 'teacher'
        $user = User::factory()->create([
            'role' => 'teacher',
        ]);

        return [
            'user_id' => $user->id,
            'employee_id' => strtoupper(Str::random(8)),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'middle_name' => $this->faker->optional()->firstName(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'specialization' => $this->faker->word(),
            'hired_date' => $this->faker->date(),
            'employment_status' => 'active'
        ];
    }
}
