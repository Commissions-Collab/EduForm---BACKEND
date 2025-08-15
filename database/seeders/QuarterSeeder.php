<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Quarter;
use Illuminate\Database\Seeder;

class QuarterSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();

        $quarterData = [
            ['name' => '1st Quarter', 'start_date' => '2025-08-15', 'end_date' => '2025-10-31'],
            ['name' => '2nd Quarter', 'start_date' => '2025-11-01', 'end_date' => '2026-01-31'],
            ['name' => '3rd Quarter', 'start_date' => '2026-02-01', 'end_date' => '2026-03-31'],
            ['name' => '4th Quarter', 'start_date' => '2026-04-01', 'end_date' => '2026-05-30'],
        ];

        foreach ($quarterData as $quarter) {
            Quarter::factory()->create([
                'academic_year_id' => $academicYear->id,
                'name' => $quarter['name'],
                'start_date' => $quarter['start_date'],
                'end_date' => $quarter['end_date']
            ]);
        }
    }
}