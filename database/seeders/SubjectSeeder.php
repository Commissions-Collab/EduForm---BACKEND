<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        // Create realistic high school subjects using factory
        $subjectData = [
            // Core Subjects (Grades 7-10)
            ['name' => 'Mathematics', 'code' => 'MATH'],
            ['name' => 'English', 'code' => 'ENG'],
            ['name' => 'Filipino', 'code' => 'FIL'],
            ['name' => 'Science', 'code' => 'SCI'],
            ['name' => 'Araling Panlipunan', 'code' => 'AP'],
            ['name' => 'Technology and Livelihood Education', 'code' => 'TLE'],
            ['name' => 'Music', 'code' => 'MUSIC'],
            ['name' => 'Arts', 'code' => 'ARTS'],
            ['name' => 'Physical Education', 'code' => 'PE'],
            ['name' => 'Health', 'code' => 'HEALTH'],
            ['name' => 'Values Education', 'code' => 'VE'],

            // Senior High Core Subjects
            ['name' => 'General Mathematics', 'code' => 'GENMATH'],
            ['name' => 'Statistics and Probability', 'code' => 'STAT'],
            ['name' => 'Earth and Life Science', 'code' => 'ELS'],
            ['name' => 'Physical Science', 'code' => 'PHYS'],

            // STEM Specialized Subjects
            ['name' => 'Pre-Calculus', 'code' => 'PRECAL', 'strand' => 'STEM'],
            ['name' => 'Basic Calculus', 'code' => 'CALCULUS', 'strand' => 'STEM'],
            ['name' => 'General Biology 1', 'code' => 'GENBI01', 'strand' => 'STEM'],
            ['name' => 'General Biology 2', 'code' => 'GENBI02', 'strand' => 'STEM'],
            ['name' => 'General Chemistry 1', 'code' => 'GENCH01', 'strand' => 'STEM'],
            ['name' => 'General Chemistry 2', 'code' => 'GENCH02', 'strand' => 'STEM'],
            ['name' => 'General Physics 1', 'code' => 'GENPH01', 'strand' => 'STEM'],
            ['name' => 'General Physics 2', 'code' => 'GENPH02', 'strand' => 'STEM'],

            // ABM Specialized Subjects
            ['name' => 'Fundamentals of Accountancy', 'code' => 'FABC', 'strand' => 'ABM'],
            ['name' => 'Business Ethics and Social Responsibility', 'code' => 'BESR', 'strand' => 'ABM'],
            ['name' => 'Business Finance', 'code' => 'BFIN', 'strand' => 'ABM'],
            ['name' => 'Business Marketing', 'code' => 'BMKT', 'strand' => 'ABM'],
            ['name' => 'Organization and Management', 'code' => 'ORGMGT', 'strand' => 'ABM'],

            // HUMSS Specialized Subjects
            ['name' => 'Creative Writing', 'code' => 'CRWRI', 'strand' => 'HUMSS'],
            ['name' => 'Creative Nonfiction', 'code' => 'CRNF', 'strand' => 'HUMSS'],
            ['name' => 'Introduction to World Religions', 'code' => 'WRLD', 'strand' => 'HUMSS'],
            ['name' => 'Trends, Networks and Critical Thinking', 'code' => 'TNCT', 'strand' => 'HUMSS'],
            ['name' => 'Philippine Politics and Governance', 'code' => 'PPG', 'strand' => 'HUMSS'],

            // Applied Subjects
            ['name' => 'Empowerment Technologies', 'code' => 'EMPTECH'],
            ['name' => 'Practical Research 1', 'code' => 'PR1'],
            ['name' => 'Practical Research 2', 'code' => 'PR2'],
            ['name' => 'Reading and Writing Skills', 'code' => 'RWS'],
            ['name' => 'Oral Communication', 'code' => 'ORALCOM'],
            ['name' => 'Understanding Culture, Society and Politics', 'code' => 'UCSP'],
        ];

        foreach ($subjectData as $subjectInfo) {
            Subject::factory()->create([
                'name' => $subjectInfo['name'],
                'code' => $subjectInfo['code'],
                'description' => $subjectInfo['name'] . ' for High School'
            ]);
        }
    }
}