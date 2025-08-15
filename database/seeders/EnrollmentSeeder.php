<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        $students = Student::all();
        $sections = Section::all();

        // Create enrollments using factory
        $studentsPerSection = $students->split($sections->count());
        foreach ($sections as $index => $section) {
            foreach ($studentsPerSection[$index] as $student) {
                Enrollment::factory()->create([
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'grade_level' => $section->year_level_id,
                    'section_id' => $section->id,
                    'enrollment_status' => 'enrolled'
                ]);
            }
        }
    }
}