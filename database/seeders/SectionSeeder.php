<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\YearLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        $yearLevels = YearLevel::all();
        
        foreach ($yearLevels as $yearLevel) {
            // Create 2-3 sections per grade level
            $sectionNames = ['A', 'B', 'C'];
            $sectionCount = rand(2, 3);
            
            for ($i = 0; $i < $sectionCount; $i++) {
                Section::create([
                    'year_level_id' => $yearLevel->id,
                    'academic_year_id' => $currentYear->id,
                    'name' => 'Section ' . $sectionNames[$i],
                    'capacity' => rand(35, 40),
                ]);
            }
        }
    }
}
