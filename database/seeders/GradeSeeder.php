<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Quarter;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        if (!$academicYear) {
            $this->command->warn('Academic year 2025-2026 not found. Skipping grade seeding.');
            return;
        }

        $quarters = Quarter::where('academic_year_id', $academicYear->id)->get();

        // For seeding purposes, treat all quarters as "completed"
        $completedQuarters = $quarters->isEmpty() ? collect() : $quarters;

        $students = Student::all();
        $teachers = Teacher::all();

        foreach ($students as $student) {
            $enrollment = Enrollment::where('student_id', $student->id)->first();

            if (!$enrollment) {
                continue;
            }

            // Get unique subject IDs for the student's section
            $subjectIds = Schedule::where('section_id', $enrollment->section_id)
                ->pluck('subject_id')
                ->unique()
                ->values();

            foreach ($subjectIds as $subjectId) {
                $schedule = Schedule::where('section_id', $enrollment->section_id)
                    ->where('subject_id', $subjectId)
                    ->first();

                foreach ($completedQuarters as $quarter) {
                    // Weighted grade type selection
                    $gradeType = collect(
                        array_merge(
                            array_fill(0, 20, 'excellent'), // 20%
                            array_fill(0, 75, 'passing'),   // 75%
                            array_fill(0, 5, 'failing')     // 5%
                        )
                    )->random();

                    Grade::factory()->{$gradeType}()->create([
                        'student_id' => $student->id,
                        'subject_id' => $subjectId,
                        'quarter_id' => $quarter->id,
                        'academic_year_id' => $academicYear->id,
                        'recorded_by' => $schedule->teacher_id ?? $teachers->random()->id,
                    ]);
                }
            }
        }

        $this->command->info('Grades seeded successfully.');
    }
}
