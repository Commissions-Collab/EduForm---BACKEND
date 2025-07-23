<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\TeacherSubject;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        $sections = Section::where('academic_year_id', $currentYear->id)->get();
        $teacherSubjects = TeacherSubject::where('academic_year_id', $currentYear->id)->get();

        $timeSlots = [
            ['08:00:00', '09:00:00'],
            ['09:00:00', '10:00:00'],
            ['10:30:00', '11:30:00'],
            ['11:30:00', '12:30:00'],
            ['13:30:00', '14:30:00'],
            ['14:30:00', '15:30:00'],
        ];

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $rooms = ['Room 101', 'Room 102', 'Room 103', 'Room 104', 'Room 105', 'Room 106'];

        foreach ($sections as $section) {
            $usedSlots = [];
            $usedRoomSlots = [];
            $usedTeacherSlots = [];

            for ($i = 0; $i < rand(5, 8); $i++) {
                $attempt = 0;
                do {
                    $attempt++;

                    $teacherSubject = $teacherSubjects->random();
                    $day = $days[array_rand($days)];
                    $timeSlot = $timeSlots[array_rand($timeSlots)];
                    $room = $rooms[array_rand($rooms)];

                    $slotKey = $day . '_' . $timeSlot[0];
                    $roomSlotKey = $room . '_' . $day . '_' . $timeSlot[0] . '_' . $currentYear->id;
                    $teacherSlotKey = $teacherSubject->teacher_id . '_' . $day . '_' . $timeSlot[0] . '_' . $currentYear->id;

                    // ✅ Check DB for actual conflicts
                    $conflictExists = Schedule::where('academic_year_id', $currentYear->id)
                        ->where('day_of_week', $day)
                        ->where('start_time', $timeSlot[0])
                        ->where(function ($query) use ($room, $teacherSubject, $section) {
                            $query->where('room', $room)
                                  ->orWhere('teacher_id', $teacherSubject->teacher_id)
                                  ->orWhere('section_id', $section->id);
                        })
                        ->exists();

                } while (
                    (
                        in_array($slotKey, $usedSlots) ||
                        in_array($roomSlotKey, $usedRoomSlots) ||
                        in_array($teacherSlotKey, $usedTeacherSlots) ||
                        $conflictExists // ✅ DB conflict
                    ) && $attempt < 10
                );

                if ($attempt >= 10) {
                    continue;
                }

                $usedSlots[] = $slotKey;
                $usedRoomSlots[] = $roomSlotKey;
                $usedTeacherSlots[] = $teacherSlotKey;

                Schedule::create([
                    'subject_id' => $teacherSubject->subject_id,
                    'teacher_id' => $teacherSubject->teacher_id,
                    'section_id' => $section->id,
                    'academic_year_id' => $currentYear->id,
                    'day_of_week' => $day,
                    'start_time' => $timeSlot[0],
                    'end_time' => $timeSlot[1],
                    'room' => $room,
                    'is_active' => true,
                ]);
            }
        }
    }
}
