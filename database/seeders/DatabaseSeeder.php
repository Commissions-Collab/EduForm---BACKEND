<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AcademicYearSeeder::class,
            YearLevelSeeder::class,
            SubjectSeeder::class,
            UserSeeder::class,
            TeacherSeeder::class,
            SectionSeeder::class,
            StudentSeeder::class,
            TeacherSubjectSeeder::class,
            SectionAdvisorSeeder::class,
            ScheduleSeeder::class,
            AcademicCalendarSeeder::class,
            AttendanceSeeder::class,
        ]);
    }
}
