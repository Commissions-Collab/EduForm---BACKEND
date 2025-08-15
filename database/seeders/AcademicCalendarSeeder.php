<?php

namespace Database\Seeders;

use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();

        // Create academic calendar events using factory
        $calendarEvents = [
            // ['date', 'type', 'title', 'is_class_day']
            ['2025-08-15', 'regular',       'First Day of Classes', true],
            ['2025-08-16', 'special_event', 'Orientation for New Students', true],
            ['2025-08-26', 'holiday',       'National Heroes Day', false],
            ['2025-09-20', 'special_event', 'Parent-Teacher Conference', true],
            ['2025-10-07', 'special_event', 'Intramurals Week', true],
            ['2025-10-28', 'exam',          'First Quarter Examinations', true],
            ['2025-11-01', 'holiday',       'All Saints Day', false],
            ['2025-11-15', 'special_event', 'Science Fair', true],
            ['2025-11-30', 'holiday',       'Bonifacio Day', false],
            ['2025-12-18', 'special_event', 'Christmas Program', true],
            ['2025-12-25', 'holiday',       'Christmas Day', false],
            ['2026-01-01', 'holiday',       'New Year\'s Day', false],
            ['2026-01-27', 'exam',          'Second Quarter Examinations', true],
            ['2026-02-14', 'special_event', 'Acquaintance Party', true],
            ['2026-02-25', 'holiday',       'EDSA People Power Anniversary', false],
            ['2026-02-28', 'special_event', 'Career Guidance Seminar', true],
            ['2026-03-24', 'exam',          'Third Quarter Examinations', true],
            ['2026-04-10', 'special_event', 'Math Quiz Bowl', true],
            ['2026-05-19', 'exam',          'Final Examinations', true],
            ['2026-05-28', 'special_event', 'Baccalaureate Mass', true],
            ['2026-05-30', 'special_event', 'Graduation Ceremony', true],
        ];

        // Academic calendar events
        collect($calendarEvents)->each(function ($event) use ($academicYear) {
            AcademicCalendar::factory()->create([
                'academic_year_id' => $academicYear->id,
                'date'             => $event[0],
                'type'             => $event[1],
                'title'            => $event[2],
                'description'      => "{$event[2]} for the {$academicYear->name} school year.",
                'is_class_day'     => $event[3],
            ]);
        });
    }
}