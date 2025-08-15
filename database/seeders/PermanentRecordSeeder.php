<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\PermanentRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class PermanentRecordSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        $students = Student::all();
        $adminUser = User::where('role', 'super_admin')->first();

        // Permanent records for all students
        $students->each(function ($student) use ($academicYear, $adminUser) {
            $enrollment = Enrollment::where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->first();

            if (!$enrollment) {
                return;
            }

            $finalAverage = fake()->numberBetween(74, 98);

            $remarks = match (true) {
                $finalAverage >= 95 => 'Passed with Highest Honors',
                $finalAverage >= 90 => 'Passed with High Honors',
                $finalAverage >= 85 => 'Passed with Honors',
                $finalAverage < 75  => 'Failed',
                default             => 'Passed',
            };

            PermanentRecord::factory()->create([
                'student_id'     => $student->id,
                'academic_year_id'    => $academicYear->id,
                'final_average'  => $finalAverage,
                'remarks'        => $remarks,
                'validated_by'   => $adminUser->id,
            ]);
        });
    }
}