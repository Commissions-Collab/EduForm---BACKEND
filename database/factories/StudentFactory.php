<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\User;
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
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        
        return [
            'user_id' => User::factory()->student(),
            'section_id' => Section::factory(),
            'lrn' => fake()->unique()->numerify('############'),
            'student_id' => 'S' . fake()->year() . str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => $firstName,
            'middle_name' => fake()->optional(0.6)->lastName(),
            'last_name' => $lastName,
            'birthday' => fake()->dateTimeBetween('-18 years', '-6 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'address' => fake()->optional(0.9)->address(),
            'phone' => fake()->optional(0.3)->phoneNumber(),
            
            // Parent/Guardian Info
            'parent_guardian_name' => fake()->name(),
            'relationship_to_student' => fake()->randomElement(['Father', 'Mother', 'Guardian', 'Grandmother', 'Grandfather']),
            'parent_guardian_phone' => fake()->phoneNumber(),
            'parent_guardian_email' => fake()->optional(0.7)->safeEmail(),
            
            'photo' => fake()->optional(0.4)->imageUrl(200, 200, 'people'),
            'enrollment_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'enrollment_status' => fake()->randomElement(['enrolled', 'enrolled', 'enrolled', 'enrolled', 'transferred']),
        ];
    }
}
