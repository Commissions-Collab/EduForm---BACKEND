<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        // 2023-2024 Academic Year
        AcademicYear::create([
            'name' => '2023-2024',
            'start_date' => '2023-06-01',
            'end_date' => '2024-05-31',
            'is_current' => false,
        ]);

        // 2024-2025 Academic Year
        AcademicYear::create([
            'name' => '2024-2025',
            'start_date' => '2024-06-01',
            'end_date' => '2025-05-31',
            'is_current' => false,
        ]);

        // 2025-2026 Academic Year (Current)
        AcademicYear::create([
            'name' => '2025-2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'is_current' => true,
        ]);
    }
}