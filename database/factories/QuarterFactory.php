<?php

namespace Database\Factories;

use App\Models\Quarter;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuarterFactory extends Factory
{
    protected $model = Quarter::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => $this->faker->randomElement(['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter']),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
        ];
    }

    public function firstQuarter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '1st Quarter',
        ]);
    }

    public function secondQuarter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '2nd Quarter',
        ]);
    }

    public function thirdQuarter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '3rd Quarter',
        ]);
    }

    public function fourthQuarter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '4th Quarter',
        ]);
    }
}