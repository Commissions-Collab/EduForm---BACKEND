<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        AcademicYear::firstOrCreate(
            ['name' => '2025-2026'],
            [
                'start_date' => '2025-08-15',
                'end_date'   => '2026-05-30',
                'is_current' => true
            ]
        );
    }
}