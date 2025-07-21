<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function getTeacherSubjects()
    {
        try {
            $teacher = Teacher::where('user_id', Auth::user()->id)->first();

            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found'
                ], 404);
            }

            $subjects = Subject::whereHas('schedules', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => [
                    'teacher' => $teacher,
                    'subjects' => $subjects
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching subjects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get students for attendance by subject and date
     */
    public function getStudentsForAttendance(Request $request)
    {
        try {
            $request->validate([
                'subject_id'  => ['required', 'exists:subjects,id'],
                'date' => ['required', 'date']
            ]);

            $teacher = Teacher::where('user_id', Auth::user()->id)->first();

            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found.'
                ], 404);
            }

            $subjectId = $request->subject_id;
            $selectedDate = $request->date;
            $dayOfWeek = Carbon::parse($selectedDate)->format('l');

            // Get schedule for selected subject and day
            $schedule = Schedule::where('subject_id', $subjectId)
                ->where('teacher_id', $teacher->id)
                ->where('day', $dayOfWeek)
                ->with(['subject', 'year_level'])
                ->first();

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'No schedule found for this subject on ' . $dayOfWeek
                ], 404);
            }

            // Get students from sections in this year level with their attendance
            $students = Student::whereHas('section', function ($query) use ($schedule) {
                $query->where('year_level_id', $schedule->year_level_id)
                    ->where('id', $schedule->section_id);
            })
                ->with([
                    'attendances' => function ($query) use ($selectedDate) {
                        $query->where('date', $selectedDate);
                    },
                    'section'
                ])
                ->get();


            // Format student data
            $studentsData = $students->map(function ($student) {
                $attendance = $student->attendances->first();

                return [
                    'id' => $student->id,
                    'LRN' => $student->LRN,
                    'first_name' => $student->first_name,
                    'middle_name' => $student->middle_name,
                    'last_name' => $student->last_name,
                    'full_name' => trim("{$student->first_name} {$student->middle_name} {$student->last_name}"),
                    'section' => $student->section ? $student->section->name : null,
                    'attendance' => $attendance ? [
                        'id' => $attendance->id,
                        'status' => $attendance->status,
                        'remarks' => $attendance->remarks,
                        'date' => $attendance->date->format('Y-m-d')
                    ] : null
                ];
            });

            $totalStudents = $students->count();

            $presentCount = $students->filter(function ($student) {
                return $student->attendances->first() && $student->attendances->first()->status === 'present';
            })->count();
            $absentCount = $students->filter(function ($student) {
                return $student->attendances->first() && $student->attendances->first()->status === 'absent';
            })->count();
            $lateCount = $students->filter(function ($student) {
                return $student->attendances->first() && $student->attendances->first()->status === 'late';
            })->count();

            $summary = [
                'total' => $totalStudents,
                'present' => $presentCount,
                'absent' => $absentCount,
                'late' => $lateCount,
                'present_percentage' => $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100, 2) : 0,
                'absent_percentage' => $totalStudents > 0 ? round(($absentCount / $totalStudents) * 100, 2) : 0,
                'late_percentage' => $totalStudents > 0 ? round(($lateCount / $totalStudents) * 100, 2) : 0,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'students' => $studentsData,
                    'schedule' => [
                        'id' => $schedule->id,
                        'day' => $schedule->day,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'subject' => $schedule->subject->name,
                        'year_level' => $schedule->year_level->name
                    ],
                    'summary' => $summary,
                    'date' => $selectedDate,
                    'day_of_week' => $dayOfWeek
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching students: ' . $e->getMessage()
            ], 500);
        }
    }
}
