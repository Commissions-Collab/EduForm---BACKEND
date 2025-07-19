<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'LRN' => $this->faker->unique()->numerify('############'), // 12 digits
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional()->firstName(),
            'last_name' => $this->faker->lastName(),
            'birthday' => $this->faker->date(),
            'gender' => 'male', // adjust if you use enum
            'parents_fullname' => $this->faker->name(),
            'relationship_to_student' => $this->faker->randomElement(['Father', 'Mother', 'Guardian']),
            'parents_number' => $this->faker->numerify('09#########'),
            'parents_email' => $this->faker->safeEmail(),
            'image' => 'https://placehold.co/400'
        ];
    }
}
