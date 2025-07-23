<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceSummary>
 */
class AttendanceSummaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalClasses = fake()->numberBetween(15, 25);
        $presentCount = fake()->numberBetween(10, $totalClasses);
        $absentCount = fake()->numberBetween(0, $totalClasses - $presentCount);
        $lateCount = fake()->numberBetween(0, 3);
        $excusedCount = fake()->numberBetween(0, 2);
        
        $attendancePercentage = $totalClasses > 0 ? round(($presentCount / $totalClasses) * 100, 2) : 0;
        
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'month' => fake()->numberBetween(1, 12),
            'year' => fake()->numberBetween(2023, 2025),
            'total_classes' => $totalClasses,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'late_count' => $lateCount,
            'excused_count' => $excusedCount,
            'attendance_percentage' => $attendancePercentage,
        ];
    }
}
