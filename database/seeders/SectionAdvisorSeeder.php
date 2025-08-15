<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\SectionAdvisor;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class SectionAdvisorSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        $sections = Section::all();
        $teachers = Teacher::all();

        // Assign one section advisor per section using factory
        foreach ($sections as $section) {
            SectionAdvisor::factory()->create([
                'section_id' => $section->id,
                'teacher_id' => $teachers->random()->id,
                'academic_year_id' => $academicYear->id,
            ]);
        }
    }
}