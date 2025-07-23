<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create previous years
        AcademicYear::create([
            'name' => '2022-2023',
            'start_date' => Carbon::create(2022, 8, 15),
            'end_date' => Carbon::create(2023, 5, 30),
            'is_current' => false,
        ]);

        AcademicYear::create([
            'name' => '2023-2024',
            'start_date' => Carbon::create(2023, 8, 15),
            'end_date' => Carbon::create(2024, 5, 30),
            'is_current' => false,
        ]);

        // Current academic year
        AcademicYear::create([
            'name' => '2024-2025',
            'start_date' => Carbon::create(2024, 8, 15),
            'end_date' => Carbon::create(2025, 5, 30),
            'is_current' => true,
        ]);
    }
}
