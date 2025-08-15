<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use Illuminate\Database\Seeder;

class TeacherSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        $teachers = Teacher::all();
        $subjects = Subject::all();

        // Assign subjects to teachers using factory
        foreach ($teachers as $teacher) {
            $teacherSubjects = $subjects->random(rand(2, 3));
            foreach ($teacherSubjects as $subject) {
                TeacherSubject::factory()->create([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'academic_year_id' => $academicYear->id
                ]);
            }
        }
    }
}