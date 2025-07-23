<?php

namespace Database\Seeders;

use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AcademicCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        
        $holidays = [
            ['2024-12-25', 'Christmas Day', 'holiday', false],
            ['2024-12-30', 'Rizal Day', 'holiday', false],
            ['2025-01-01', 'New Year\'s Day', 'holiday', false],
            ['2025-02-14', 'Valentine\'s Day', 'special_event', true],
            ['2025-04-09', 'Araw ng Kagitingan', 'holiday', false],
            ['2025-04-17', 'Maundy Thursday', 'holiday', false],
            ['2025-04-18', 'Good Friday', 'holiday', false],
            ['2025-06-12', 'Independence Day', 'holiday', false],
        ];
        
        foreach ($holidays as $holiday) {
            AcademicCalendar::create([
                'academic_year_id' => $currentYear->id,
                'date' => $holiday[0],
                'title' => $holiday[1],
                'type' => $holiday[2],
                'is_class_day' => $holiday[3],
            ]);
        }
        
        // Add some exam periods
        AcademicCalendar::create([
            'academic_year_id' => $currentYear->id,
            'date' => '2024-10-15',
            'title' => 'First Quarter Examinations Begin',
            'type' => 'exam',
            'is_class_day' => true,
        ]);
        
        AcademicCalendar::create([
            'academic_year_id' => $currentYear->id,
            'date' => '2025-01-20',
            'title' => 'Second Quarter Examinations Begin',
            'type' => 'exam',
            'is_class_day' => true,
        ]);
    }
}
