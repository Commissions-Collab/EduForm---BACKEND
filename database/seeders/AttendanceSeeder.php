<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Quarter;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('name', '2025-2026')->first();
        $quarters = Quarter::where('academic_year_id', $academicYear->id)->get();
        $students = Student::all();

        // Create attendance records using factory
        foreach ($students as $student) {
            $enrollment = Enrollment::where('student_id', $student->id)->first();
            if ($enrollment) {
                $studentSchedules = Schedule::where('section_id', $enrollment->section_id)->take(4)->get();

                // Generate attendance for 40 school days
                for ($i = 0; $i < 40; $i++) {
                    $attendanceDate = Carbon::parse($academicYear->start_date)->addWeekdays($i);

                    foreach ($studentSchedules as $schedule) {
                        // Generate realistic attendance patterns
                        $attendanceType = collect(
                            array_merge(
                                array_fill(0, 85, 'present'),
                                array_fill(0, 10, 'absent'),
                                array_fill(0, 5, 'late')
                            )
                        )->random();

                        if ($attendanceType === 'present') {
                            Attendance::factory()->present()->create([
                                'student_id' => $student->id,
                                'schedule_id' => $schedule->id,
                                'academic_year_id' => $academicYear->id,
                                'quarter_id' => $quarters->first()->id,
                                'attendance_date' => $attendanceDate,
                                'recorded_by' => $schedule->teacher_id,
                            ]);
                        } elseif ($attendanceType === 'absent') {
                            Attendance::factory()->absent()->create([
                                'student_id' => $student->id,
                                'schedule_id' => $schedule->id,
                                'academic_year_id' => $academicYear->id,
                                'quarter_id' => $quarters->first()->id,
                                'attendance_date' => $attendanceDate,
                                'recorded_by' => $schedule->teacher_id,
                            ]);
                        } else { // late
                            Attendance::factory()->late()->create([
                                'student_id' => $student->id,
                                'schedule_id' => $schedule->id,
                                'academic_year_id' => $academicYear->id,
                                'quarter_id' => $quarters->first()->id,
                                'attendance_date' => $attendanceDate,
                                'recorded_by' => $schedule->teacher_id,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
