<?php

namespace Database\Factories;

use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicCalendarFactory extends Factory
{
    protected $model = AcademicCalendar::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'date' => $this->faker->dateTimeBetween('2023-06-01', '2026-05-31')->format('Y-m-d'),
            'type' => $this->faker->randomElement(['regular', 'holiday', 'exam', 'no_class', 'special_event']),
            'title' => $this->faker->optional()->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'is_class_day' => $this->faker->boolean(80), // 80% chance of being a class day
        ];
    }

    public function holiday(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'holiday',
            'is_class_day' => false,
            'title' => $this->faker->randomElement([
                'Independence Day',
                'Christmas Day',
                'New Year\'s Day',
                'Rizal Day',
                'Labor Day',
                'EDSA Revolution Anniversary'
            ]),
        ]);
    }

    public function exam(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'exam',
            'is_class_day' => true,
            'title' => $this->faker->randomElement([
                'Quarterly Examination',
                'Final Examination',
                'Midterm Examination'
            ]),
        ]);
    }
}