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
        $type = $this->faker->randomElement(['regular', 'holiday', 'exam', 'no_class', 'special_event']);
        $isClassDay = !in_array($type, ['holiday', 'no_class']);

        return [
            'academic_year_id' => AcademicYear::factory(),
            'date' => $this->faker->dateTimeBetween('2024-08-01', '2025-05-31')->format('Y-m-d'),
            'type' => $type,
            'title' => $this->faker->optional()->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'is_class_day' => $isClassDay,
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
