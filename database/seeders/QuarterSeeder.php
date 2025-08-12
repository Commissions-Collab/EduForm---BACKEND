<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Quarter;
use Illuminate\Database\Seeder;

class QuarterSeeder extends Seeder
{
    public function run(): void
    {
        $academicYears = AcademicYear::all();

        foreach ($academicYears as $academicYear) {
            $startDate = \Carbon\Carbon::parse($academicYear->start_date);
            
            // 1st Quarter
            Quarter::create([
                'academic_year_id' => $academicYear->id,
                'name' => '1st Quarter',
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $startDate->copy()->addMonths(2)->subDay()->format('Y-m-d'),
            ]);

            // 2nd Quarter
            $secondStart = $startDate->copy()->addMonths(2);
            Quarter::create([
                'academic_year_id' => $academicYear->id,
                'name' => '2nd Quarter',
                'start_date' => $secondStart->format('Y-m-d'),
                'end_date' => $secondStart->copy()->addMonths(3)->subDay()->format('Y-m-d'),
            ]);

            // 3rd Quarter
            $thirdStart = $startDate->copy()->addMonths(5);
            Quarter::create([
                'academic_year_id' => $academicYear->id,
                'name' => '3rd Quarter',
                'start_date' => $thirdStart->format('Y-m-d'),
                'end_date' => $thirdStart->copy()->addMonths(2)->subDay()->format('Y-m-d'),
            ]);

            // 4th Quarter
            $fourthStart = $startDate->copy()->addMonths(7);
            Quarter::create([
                'academic_year_id' => $academicYear->id,
                'name' => '4th Quarter',
                'start_date' => $fourthStart->format('Y-m-d'),
                'end_date' => $academicYear->end_date,
            ]);
        }
    }
}