<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Quarter;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'quarter_id' => Quarter::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'grade' => $this->faker->randomFloat(2, 75, 100), // Philippine grading system 75-100
            'recorded_by' => User::factory()->teacher(),
        ];
    }

    public function passing(): static
    {
        return $this->state(fn (array $attributes) => [
            'grade' => $this->faker->randomFloat(2, 75, 95),
        ]);
    }

    public function failing(): static
    {
        return $this->state(fn (array $attributes) => [
            'grade' => $this->faker->randomFloat(2, 60, 74),
        ]);
    }

    public function excellent(): static
    {
        return $this->state(fn (array $attributes) => [
            'grade' => $this->faker->randomFloat(2, 90, 100),
        ]);
    }
}