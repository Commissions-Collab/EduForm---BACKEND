<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\YearLevel;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    public function run(): void
    {
        $yearLevels = YearLevel::all();

        // Create high school sections using factory
        $sectionData = [
            // Grade 7 (1st Year)
            ['name' => 'Grade 7 - Einstein', 'grade_level' => 7],
            ['name' => 'Grade 7 - Newton', 'grade_level' => 7],
            ['name' => 'Grade 7 - Galileo', 'grade_level' => 7],
            ['name' => 'Grade 7 - Darwin', 'grade_level' => 7],

            // Grade 8 (2nd Year)
            ['name' => 'Grade 8 - Tesla', 'grade_level' => 8],
            ['name' => 'Grade 8 - Edison', 'grade_level' => 8],
            ['name' => 'Grade 8 - Curie', 'grade_level' => 8],
            ['name' => 'Grade 8 - Pasteur', 'grade_level' => 8],

            // Grade 9 (3rd Year)
            ['name' => 'Grade 9 - Hawking', 'grade_level' => 9],
            ['name' => 'Grade 9 - Feynman', 'grade_level' => 9],
            ['name' => 'Grade 9 - Bohr', 'grade_level' => 9],
            ['name' => 'Grade 9 - Planck', 'grade_level' => 9],

            // Grade 10 (4th Year)
            ['name' => 'Grade 10 - Leonardo', 'grade_level' => 10],
            ['name' => 'Grade 10 - Aristotle', 'grade_level' => 10],
            ['name' => 'Grade 10 - Archimedes', 'grade_level' => 10],

            // Grade 11 (Senior High - 1st Year)
            ['name' => 'Grade 11 - STEM A', 'grade_level' => 11, 'strand' => 'STEM'],
            ['name' => 'Grade 11 - STEM B', 'grade_level' => 11, 'strand' => 'STEM'],
            ['name' => 'Grade 11 - ABM', 'grade_level' => 11, 'strand' => 'ABM'],
            ['name' => 'Grade 11 - HUMSS', 'grade_level' => 11, 'strand' => 'HUMSS'],
            ['name' => 'Grade 11 - GAS', 'grade_level' => 11, 'strand' => 'GAS'],

            // Grade 12 (Senior High - 2nd Year)
            ['name' => 'Grade 12 - STEM A', 'grade_level' => 12, 'strand' => 'STEM'],
            ['name' => 'Grade 12 - STEM B', 'grade_level' => 12, 'strand' => 'STEM'],
            ['name' => 'Grade 12 - ABM', 'grade_level' => 12, 'strand' => 'ABM'],
            ['name' => 'Grade 12 - HUMSS', 'grade_level' => 12, 'strand' => 'HUMSS'],
            ['name' => 'Grade 12 - GAS', 'grade_level' => 12, 'strand' => 'GAS'],
        ];

        foreach ($sectionData as $data) {
            $yearLevel = $yearLevels->firstWhere('name', 'Grade ' . $data['grade_level']);

            if (!$yearLevel) {
                throw new \Exception("No year level found for Grade {$data['grade_level']}");
            }

            Section::factory()->create([
                'name' => $data['name'],
                'year_level_id' => $yearLevel->id,
                'strand' => $data['strand'] ?? null,
                'capacity' => rand(35, 45),
                'room' => 'Room ' . rand(201, 350),
            ]);
        }
    }
}