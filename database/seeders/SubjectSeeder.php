<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            ['name' => 'Mathematics', 'code' => 'MATH101', 'description' => 'Basic Mathematics', 'units' => 1],
            ['name' => 'English', 'code' => 'ENG101', 'description' => 'English Language Arts', 'units' => 1],
            ['name' => 'Filipino', 'code' => 'FIL101', 'description' => 'Filipino Language', 'units' => 1],
            ['name' => 'Science', 'code' => 'SCI101', 'description' => 'Basic Science', 'units' => 1],
            ['name' => 'Social Studies', 'code' => 'SS101', 'description' => 'Social Studies', 'units' => 1],
            ['name' => 'Physical Education', 'code' => 'PE101', 'description' => 'Physical Education and Health', 'units' => 1],
            ['name' => 'Music', 'code' => 'MUS101', 'description' => 'Music Education', 'units' => 1],
            ['name' => 'Arts', 'code' => 'ARTS101', 'description' => 'Visual Arts', 'units' => 1],
            ['name' => 'Values Education', 'code' => 'VE101', 'description' => 'Values and Character Education', 'units' => 1],
            ['name' => 'Health', 'code' => 'HEALTH101', 'description' => 'Health Education', 'units' => 1],
        ];

        foreach ($subjects as $subject) {
            Subject::create($subject);
        }
    }
}
