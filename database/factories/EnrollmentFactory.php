<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\YearLevel;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            // Corrected foreign key name from 'grade_level' to 'year_level_id'
            'grade_level' => YearLevel::factory(),
            'section_id' => Section::factory(),
            'enrollment_status' => $this->faker->randomElement(['enrolled', 'pending', 'transferred', 'dropped']),
        ];
    }

    /**
     * Indicate that the student is officially enrolled.
     */
    public function enrolled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrollment_status' => 'enrolled',
        ]);
    }
}
