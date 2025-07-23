<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\SectionAdvisor;
use App\Models\Teacher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectionAdvisorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        $sections = Section::where('academic_year_id', $currentYear->id)->get();
        $teachers = Teacher::all();
        
        foreach ($sections as $section) {
            SectionAdvisor::create([
                'section_id' => $section->id,
                'teacher_id' => $teachers->random()->id,
                'academic_year_id' => $currentYear->id,
            ]);
        }
    }
}
