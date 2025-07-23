<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AcademicCalendar>
 */
class AcademicCalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'date' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'type' => fake()->randomElement(['regular', 'holiday', 'exam', 'no_class', 'special_event']),
            'title' => fake()->optional(0.7)->sentence(3),
            'description' => fake()->optional(0.5)->paragraph(),
            'is_class_day' => fake()->boolean(80),
        ];
    }
}
