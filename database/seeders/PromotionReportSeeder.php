<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\PromotionReport;
use App\Models\Student;
use Illuminate\Database\Seeder;

class PromotionReportSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        $students = Student::all();

        // Promotion reports for all students
        $students->each(function ($student) use ($academicYear) {
            $enrollment = Enrollment::where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->first();

            if (!$enrollment) {
                return;
            }

            $finalAverage = fake()->numberBetween(74, 98);

            PromotionReport::factory()->create([
                'student_id'       => $student->id,
                'academic_year_id' => $academicYear->id,
                'final_average'    => $finalAverage,
                'year_level_id'    => $enrollment->grade_level,
            ]);
        });
    }
}