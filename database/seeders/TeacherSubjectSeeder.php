<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeacherSubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        $teachers = Teacher::all();
        $subjects = Subject::all();
        
        foreach ($teachers as $teacher) {
            // Each teacher teaches 2-4 subjects
            $teacherSubjects = $subjects->random(rand(2, 4));
            
            foreach ($teacherSubjects as $subject) {
                TeacherSubject::create([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'academic_year_id' => $currentYear->id,
                ]);
            }
        }
    }
}
