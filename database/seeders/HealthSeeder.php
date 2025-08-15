<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\HealthProfile;
use App\Models\Quarter;
use App\Models\Student;
use App\Models\StudentBMI;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class HealthSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        $quarters = Quarter::where('academic_year_id', $academicYear->id)->get();
        $students = Student::all();

        // Get the Super Admin user to use for 'updated_by'
        $adminUser = User::where('role', 'super_admin')->first();

        // Create BMI and Health Profile records for each student
        foreach ($students as $student) {
            // 1. Generate realistic base data for a high school student
            $heightCm = rand(145, 175); // Height in cm for students aged ~12-18
            $weightKg = rand(40, 75);   // Weight in kg

            // 2. Calculate BMI
            $heightM = $heightCm / 100; // Convert height to meters for calculation
            $bmi = round($weightKg / ($heightM * $heightM), 2);

            // 3. Determine the BMI category based on the result
            if ($bmi < 18.5) {
                $bmiCategory = 'Underweight';
            } elseif ($bmi >= 18.5 && $bmi < 25) {
                $bmiCategory = 'Normal weight';
            } elseif ($bmi >= 25 && $bmi < 30) {
                $bmiCategory = 'Overweight';
            } else {
                $bmiCategory = 'Obese';
            }

            // 4. Create the StudentBMI record with all required fields
            StudentBMI::factory()->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'quarter_id' => $quarters->first()->id, // Default to the 1st quarter
                'recorded_at' => Carbon::parse($academicYear->start_date)->addDays(rand(30, 60)),
                'height_cm' => $heightCm,
                'weight_kg' => $weightKg,
                'bmi' => $bmi,
                'bmi_category' => $bmiCategory,
                'remarks' => 'Initial measurement for the school year.',
            ]);

            // 5. Create the HealthProfile record with its required fields
            HealthProfile::factory()->create([
                'student_id' => $student->id,
                'height' => $heightCm,
                'weight' => $weightKg,
                'notes' => 'No immediate health concerns noted during initial check.',
                'updated_by' => $adminUser->id,
            ]);
        }
    }
}