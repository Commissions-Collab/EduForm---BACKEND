<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\YearLevel;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'year_level_id' => YearLevel::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Section ' . $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']),
            'capacity' => $this->faker->numberBetween(30, 45),
        ];
    }
}