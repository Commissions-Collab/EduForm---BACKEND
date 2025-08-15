<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AcademicYearSeeder::class,
            QuarterSeeder::class,
            YearLevelSeeder::class,
            TeacherSeeder::class,
            StudentSeeder::class,
            SectionSeeder::class,
            SectionAdvisorSeeder::class,
            SubjectSeeder::class,
            TeacherSubjectSeeder::class,
            ScheduleSeeder::class,
            EnrollmentSeeder::class,
            AttendanceSeeder::class,
            GradeSeeder::class,
            BookInventorySeeder::class,
            StudentBorrowBookSeeder::class,
            HealthSeeder::class,
            PermanentRecordSeeder::class,
            AcademicCalendarSeeder::class,
            PromotionReportSeeder::class,
        ]);
    }
}
