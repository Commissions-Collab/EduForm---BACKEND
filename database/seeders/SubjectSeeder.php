<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Mathematics', 'code' => 'MATH101', 'description' => 'Basic Mathematics', 'units' => 1],
            ['name' => 'English', 'code' => 'ENG101', 'description' => 'English Language', 'units' => 1],
            ['name' => 'Science', 'code' => 'SCI101', 'description' => 'General Science', 'units' => 1],
            ['name' => 'Filipino', 'code' => 'FIL101', 'description' => 'Filipino Language', 'units' => 1],
            ['name' => 'Araling Panlipunan', 'code' => 'AP101', 'description' => 'Social Studies', 'units' => 1],
            ['name' => 'Technology and Livelihood Education', 'code' => 'TLE101', 'description' => 'Technical Skills', 'units' => 1],
            ['name' => 'Music, Arts, Physical Education and Health', 'code' => 'MAPEH101', 'description' => 'Creative and Physical Education', 'units' => 1],
            ['name' => 'Values Education', 'code' => 'VE101', 'description' => 'Character Building', 'units' => 1],
            ['name' => 'Computer Education', 'code' => 'CE101', 'description' => 'Basic Computer Skills', 'units' => 1],
            ['name' => 'Research', 'code' => 'RES101', 'description' => 'Research Methodology', 'units' => 1],
        ];

        foreach ($subjects as $subject) {
            Subject::create(array_merge($subject, ['is_active' => true]));
        }
    }
}