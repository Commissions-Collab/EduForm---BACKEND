<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\YearLevel;
use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    public function run(): void
    {
        $yearLevels = YearLevel::all();
        $academicYears = AcademicYear::all();
        $sectionNames = ['Section A', 'Section B', 'Section C'];

        foreach ($academicYears as $academicYear) {
            foreach ($yearLevels as $yearLevel) {
                foreach ($sectionNames as $sectionName) {
                    Section::create([
                        'year_level_id' => $yearLevel->id,
                        'academic_year_id' => $academicYear->id,
                        'name' => $sectionName,
                        'capacity' => fake()->numberBetween(35, 45),
                    ]);
                }
            }
        }
    }
}