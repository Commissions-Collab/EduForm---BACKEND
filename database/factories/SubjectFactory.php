<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Math',
                'Science',
                'English',
                'Filipino',
                'AP',
                'ESP',
                'TLE',
                'MAPEH',
                'PE',
                'Computer',
            ]),

            // Assigning a random teacher_id (you can override this when calling)
            'advisor_id' => User::factory()->create(['role' => 'teacher'])->id,
        ];
    }
}
