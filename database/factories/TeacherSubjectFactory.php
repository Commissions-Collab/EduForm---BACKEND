<?php

namespace Database\Factories;

use App\Models\TeacherSubject;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Quarter;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherSubjectFactory extends Factory
{
    protected $model = TeacherSubject::class;

    public function definition()
    {
        return [
            'teacher_id' => Teacher::inRandomOrder()->first()->id ?? Teacher::factory(),
            'subject_id' => Subject::inRandomOrder()->first()->id ?? Subject::factory(),
            'academic_year_id' => AcademicYear::where('is_current', true)->first()->id
                ?? AcademicYear::factory()->create(['is_current' => true])->id,
        ];
    }
}