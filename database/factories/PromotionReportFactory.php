<?php

namespace Database\Factories;

use App\Models\PromotionReport;
use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionReportFactory extends Factory
{
    protected $model = PromotionReport::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'final_average' => $this->faker->randomFloat(2, 70, 98),
            'year_level_id' => YearLevel::factory(),
        ];
    }

    /**
     * Indicate that the student is promoted.
     */
    public function promoted(): static
    {
        return $this->state(fn (array $attributes) => [
            'final_average' => $this->faker->randomFloat(2, 75, 98),
        ]);
    }

    /**
     * Indicate that the student is retained.
     */
    public function retained(): static
    {
        return $this->state(fn (array $attributes) => [
            'final_average' => $this->faker->randomFloat(2, 65, 74.99),
        ]);
    }
}
