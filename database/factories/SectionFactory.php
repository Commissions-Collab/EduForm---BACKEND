<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\YearLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year_level_id' => YearLevel::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Section ' . fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'capacity' => fake()->numberBetween(30, 45),
        ];
    }
}
