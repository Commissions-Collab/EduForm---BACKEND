<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Student;
use App\Models\Subject;
use App\Models\YearLevel;
use App\Models\Schedule;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin
        $admin = User::factory()->admin()->create([
            'email' => 'superAdmin@gmail.com',
            'password' => bcrypt('secret'),
        ]);

        // Create Teachers
        $teacher1 = User::factory()->teacher()->create([
            'email' => 'registrar@gmail.com',
            'password' => bcrypt('secret'),
        ]);

        $teacher2 = User::factory()->teacher()->create([
            'email' => 'teacher2@gmail.com',
            'password' => bcrypt('secret'),
        ]);

        // Create Generic Users
        User::factory()->count(10)->create();

        // Create Students
        Student::factory()->count(10)->create();

        // Create Year Levels
        $yearLevels = YearLevel::factory()->count(4)->create([
            'admin_id' => $admin->id
        ]);

        Teacher::factory()->count(10)->create();

        // Create Subjects
        $subjects1 = Subject::factory()->count(6)->create([
            'advisor_id' => $teacher1->id,
        ]);

        $subjects2 = Subject::factory()->count(4)->create([
            'advisor_id' => $teacher2->id,
        ]);

        $subjects = $subjects1->merge($subjects2);

        // Create Schedules
        foreach (range(1, 25) as $i) {
            $subject = $subjects->random();
            Schedule::factory()->create([
                'subject_id' => $subject->id,
                'teacher_id' => $subject->advisor_id, // fixed here
                'year_level_id' => $yearLevels->random()->id,
                'admin_id' => $admin->id,
            ]);
        }
    }
}
