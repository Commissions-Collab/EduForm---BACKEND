<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AttendanceHelper;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Quarter;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Get weekly class schedule for the authenticated teacher
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getWeeklySchedule(Request $request): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found'
                ], 404);
            }

            // Get current academic year or allow override via request
            $academicYear = $this->getCurrentAcademicYear($request->get('academic_year_id'));

            if (!$academicYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic year not found',
                ], 404);
            }

            // Get week dates (default to current week or specified week)
            $weekStart = $request->get('week_start')
                ? Carbon::parse($request->get('week_start'))->startOfWeek()
                : Carbon::now()->startOfWeek();

            $weekEnd = $weekStart->copy()->endOfWeek();

            // Get teacher's schedules for the academic year
            $schedules = Schedule::with([
                'subject:id,name,code',
                'section:id,name',
                'section.yearLevel:id,name',
                'scheduleExceptions' => function ($query) use ($weekStart, $weekEnd) {
                    $query->whereBetween('date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
                }
            ])
                ->where('teacher_id', $teacher->id)
                ->where('academic_year_id', $academicYear->id)
                ->where('is_active', true)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            // Get academic calendar for the week (holidays, special events)
            $calendarEvents = $this->getWeekCalendarEvents($academicYear->id, $weekStart, $weekEnd);

            // Format schedule by days
            $weeklySchedule = $this->formatWeeklySchedule($schedules, $weekStart, $calendarEvents);

            return response()->json([
                'success' => true,
                'data' => [
                    'academic_year' => $academicYear ? [
                        'id' => $academicYear->id,
                        'name' => $academicYear->name
                    ] : null,
                    'week_period' => [
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d'),
                        'week_number' => $weekStart->weekOfYear
                    ],
                    'schedule' => $weeklySchedule,
                    'calendar_events' => $calendarEvents
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch weekly schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getScheduleStudents($scheduleId, Request $request): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;

            // Verify the schedule belongs to the authenticated teacher
            $schedule = Schedule::with([
                'subject:id,name,code',
                'section:id,name',
                'section.yearLevel:id,name',
                'teacher:id,first_name,last_name'
            ])
                ->where('id', $scheduleId)
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->first();

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule not found or access denied'
                ], 404);
            }

            // Get attendance date (default to today or specified date)
            $attendanceDate = $request->get('date') ? Carbon::parse($request->get('date'))->format('Y-m-d') : Carbon::now()->format('Y-m-d');

            // Validate that the date matches the schedule's day of week
            $dayOfWeek = Carbon::parse($attendanceDate)->format('l');
            if ($dayOfWeek !== $schedule->day_of_week) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected date does not match the schedule day'
                ], 400);
            }

            // Check for schedule exceptions
            $exception = ScheduleException::where('schedule_id', $scheduleId)
                ->where('date', $attendanceDate)
                ->first();

            if ($exception && $exception->type === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Class is cancelled for this date',
                    'exception' => $exception
                ], 400);
            }

            // Get students in the section with existing attendance
            $students = Student::with([
                'user:id,email',
                'attendances' => function ($query) use ($scheduleId, $attendanceDate) {
                    $query->where('schedule_id', $scheduleId)
                        ->where('attendance_date', $attendanceDate);
                },
                'enrollments'
            ])
                ->whereHas('enrollments', function ($query) use ($schedule) {
                    $query->where('enrollment_status', 'enrolled')
                        ->where('section_id', $schedule->section_id);
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            // Format student data with attendance status
            $studentsData = $students->map(function ($student) {
                $attendance = $student->attendances->first();

                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'lrn' => $student->lrn,
                    'full_name' => trim($student->first_name . ' ' . $student->middle_name . ' ' . $student->last_name),
                    'first_name' => $student->first_name,
                    'middle_name' => $student->middle_name,
                    'last_name' => $student->last_name,
                    'photo' => $student->photo,
                    'attendance' => $attendance ? [
                        'id' => $attendance->id,
                        'status' => $attendance->status,
                        'time_in' => $attendance->time_in,
                        'time_out' => $attendance->time_out,
                        'remarks' => $attendance->remarks,
                        'recorded_at' => $attendance->recorded_at
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule' => [
                        'id' => $schedule->id,
                        'subject' => $schedule->subject,
                        'section' => $schedule->section,
                        'day_of_week' => $schedule->day_of_week,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'room' => $schedule->room
                    ],
                    'attendance_date' => $attendanceDate,
                    'exception' => $exception,
                    'students' => $studentsData,
                    'summary' => [
                        'total_students' => $studentsData->count(),
                        'present' => $studentsData->where('attendance.status', 'present')->count(),
                        'absent' => $studentsData->where('attendance.status', 'absent')->count(),
                        'late' => $studentsData->where('attendance.status', 'late')->count(),
                        'excused' => $studentsData->where('attendance.status', 'excused')->count(),
                        'not_recorded' => $studentsData->whereNull('attendance')->count()
                    ]
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schedule students',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function updateIndividualAttendance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'schedule_id' => 'required|exists:schedules,id',
            'attendance_date' => 'required|date',
            'status' => 'required|in:present,absent,late,excused',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'remarks' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'erros' => $validator->errors()
            ], 422);
        }

        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($request->schedule_id, $teacher->id);

            if (!$schedule) {
                return $this->errorResponse('Schedule not found or accesss denied', 404);
            }


            $academicYear = $this->getCurrentAcademicYear();

            Log::debug($academicYear->id);
            DB::beginTransaction();

            // update or create attendance record
            $attendance = Attendance::updateOrCreate(
                [
                    'student_id' => $request->student_id,
                    'schedule_id' => $request->schedule_id,
                    'attendance_date' => $request->attendance_date
                ],
                [
                    'academic_year_id' => $academicYear->id,
                    'status' => $request->status,
                    'time_in' => $request->time_in,
                    'time_out' => $request->time_out,
                    'remarks' => $request->remarks,
                    'recorded_by' => Auth::id(),
                    'recorded_at' => now()
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attendance updated successfully',
                'data' => [
                    'attendance' => $attendance
                ]
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function updateBulkAttendance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|exists:schedules,id',
            'attendance_date' => 'required|date',
            'attendances' => 'required|array|min:1',
            'attendances.*.student_id' => 'required|exists:students,id',
            'attendances.*.status' => 'required|in:present,absent,late,excused',
            'attendances.*.time_in' => 'nullable|date_format:H:i',
            'attendances.*.time_out' => 'nullable|date_format:H:i',
            'attendances.*.remarks' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'erros' => $validator->errors()
            ], 422);
        }

        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($request->schedule_id, $teacher->id);

            if (!$schedule) {
                return $this->errorResponse('Schedule not found or accesss denied', 404);
            }


            $academicYear = $this->getCurrentAcademicYear();
            $currentQuarter = $this->getCurrentQuarter($academicYear->id);
            $updatedAttendances = [];

            DB::beginTransaction();

            foreach ($request->attendances as $attendanceData) {
                // update or create attendance record
                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $attendanceData['student_id'],
                        'schedule_id' => $request->schedule_id,
                        'attendance_date' => $request->attendance_date
                    ],
                    [
                        'academic_year_id' => $academicYear->id,
                        'quarter_id' => $currentQuarter->id,
                        'status' => $attendanceData['status'],
                        'time_in' => $attendanceData['time_in'] ?? null,
                        'time_out' => $attendanceData['time_out'] ?? null,
                        'remarks' => $attendanceData['remarks'] ?? null,
                        'recorded_by' => Auth::id(),
                        'recorded_at' => now()
                    ]
                );

                $updatedAttendances[] = $attendance;
            }


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk attendance updated successfully',
                'data' => [
                    'updated_count' => count($updatedAttendances),
                    'attendances' => $updatedAttendances
                ]
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bulk attendance',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function updateAllStudentsAttendance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|exists:schedules,id',
            'attendance_date' => 'required|date',
            'status' => 'required|in:present,absent,late,excused',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'remarks' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'erros' => $validator->errors()
            ], 422);
        }

        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($request->schedule_id, $teacher->id);

            if (!$schedule) {
                return $this->errorResponse('Schedule not found or accesss denied', 404);
            }
            $students = Student::with(['enrollments'])->whereHas('enrollments', function ($query) use ($schedule) {
                $query->where('enrollment_status', 'enrolled')
                    ->where('section_id', $schedule->section_id);
            })
                ->get();

            $academicYear = $this->getCurrentAcademicYear();
            $currentQuarter = $this->getCurrentQuarter($academicYear->id);
            $updatedAttendances = [];

            DB::beginTransaction();

            foreach ($students as $student) {
                // update or create attendance record
                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'schedule_id' => $request->schedule_id,
                        'attendance_date' => $request->attendance_date
                    ],
                    [
                        'academic_year_id' => $academicYear->id,
                        'quarter_id' => $currentQuarter->id,
                        'status' => $request->status,
                        'time_in' => $request->time_in,
                        'time_out' => $request->time_out,
                        'remarks' => $request->remarks,
                        'recorded_by' => Auth::id(),
                        'recorded_at' => now()
                    ]
                );

                $updatedAttendances[] = $attendance;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'All students attendance updated successfully',
                'data' => [
                    'updated_count' => count($updatedAttendances),
                    'total_students' => $students->count(),
                    'status_applied' => $request->status,
                    'attendances' => $updatedAttendances
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update all students attendance', 500, $e->getMessage());
        }
    }

    public function getAttendanceHistory($scheduleId, Request $request): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($scheduleId, $teacher->id);

            if (!$schedule) {
                return $this->errorResponse('Schedule not found or accesss denied', 404);
            }

            // Get date range (default to current month)
            $startDate = $request->get('start_date')
                ? Carbon::parse($request->get('start_date'))
                : Carbon::now()->startOfMonth();

            $endDate = $request->get('end_date')
                ? Carbon::parse($request->get('end_date'))
                : Carbon::now()->endOfMonth();

            // Get attendance records
            $attendances = Attendance::with(['student:id,student_id,first_name,last_name'])
                ->where('schedule_id', $scheduleId)
                ->whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('attendance_date', 'desc')
                ->orderBy('student_id')
                ->get()
                ->groupBy('attendance_date');

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule' => $schedule,
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d')
                    ],
                    'attendance_history' => $attendances
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch attendance history', 500, $e->getMessage());
        }
    }

    public function getStudentAttendanceHistory($studentId, $scheduleId, Request $request): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($scheduleId, $teacher->id);

            if (!$schedule) {
                return $this->errorResponse('Schedule not found or accesss denied', 404);
            }

            // Verify student belongs to the section
            $student = AttendanceHelper::verifyStudentAccess($studentId, $schedule->section_id);

            if (!$student) {
                return $this->errorResponse('Student not found or not enrolled in this section', 404);
            }

            // Get current academic year
            $academicYear = $this->getCurrentAcademicYear($request->get('academic_year_id'));

            // Get date range (default to current academic year or custom range)
            $dateRange = AttendanceHelper::getDateRange($request, $academicYear);

            // Get all attendance records for this student in this subject
            $attendances = AttendanceHelper::getAttendanceRecords($studentId, $scheduleId, $academicYear->id, $dateRange);

            // Calculate statistics and format data
            $attendanceSummary = AttendanceHelper::calculateAttendanceSummary($attendances);
            $monthlyBreakdown = AttendanceHelper::getMonthlyBreakdown($attendances);
            $attendanceRecords = AttendanceHelper::formatAttendanceRecords($attendances);

            return response()->json([
                'success' => true,
                'data' => [
                    'student' => AttendanceHelper::formatStudentData($student),
                    'subject' => AttendanceHelper::formatSubjectData($schedule->subject),
                    'section' => AttendanceHelper::formatSectionData($schedule->section),
                    'academic_year' => AttendanceHelper::formatAcademicYearData($academicYear),
                    'period' => [
                        'start_date' => $dateRange['start']->format('Y-m-d'),
                        'end_date' => $dateRange['end']->format('Y-m-d')
                    ],
                    'attendance_summary' => $attendanceSummary,
                    'monthly_breakdown' => $monthlyBreakdown,
                    'attendance_records' => $attendanceRecords,
                    'recent_activity' => $attendanceRecords->take(10)
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch student attendance history', 500, $e->getMessage());
        }
    }

    // Helper
    private function getCurrentAcademicYear($academicYearId = null)
    {
        if ($academicYearId) {
            return AcademicYear::findOrFail($academicYearId);
        }

        return AcademicYear::where('is_current', true)->firstOrFail();
    }

    private function getCurrentQuarter($academicYearId)
    {
        $today = Carbon::today();

        // First try to find a quarter where today falls between start_date and end_date
        $currentQuarter = Quarter::where('academic_year_id', $academicYearId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        // If no current quarter found, fall back to the one marked as current
        if (!$currentQuarter) {
            $currentQuarter = Quarter::where('academic_year_id', $academicYearId)
                ->where('is_current', true)
                ->first();
        }

        // Final fallback to first quarter by start date
        if (!$currentQuarter) {
            $currentQuarter = Quarter::where('academic_year_id', $academicYearId)
                ->orderBy('start_date')
                ->first();
        }

        return $currentQuarter;
    }

    private function getWeekCalendarEvents($academicYearId, $weekStart, $weekEnd)
    {
        return DB::table('academic_calendars')
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->get();
    }

    private function formatWeeklySchedule($schedules, $weekStart, $calendarEvents)
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $weeklySchedule = [];

        foreach ($days as $day) {
            $currentDate = $weekStart->copy()->startOfWeek()->addDays(array_search($day, $days));

            // Get calendar event for this date
            $calendarEvent = collect($calendarEvents)->firstWhere('date', $currentDate->format('Y-m-d'));

            $daySchedules = $schedules->where('day_of_week', $day)->map(function ($schedule) use ($currentDate) {
                // Check for exceptions on this date
                $exception = $schedule->scheduleExceptions->firstWhere('date', $currentDate->format('Y-m-d'));

                return [
                    'id' => $schedule->id,
                    'subject' => [
                        'id' => $schedule->subject->id,
                        'name' => $schedule->subject->name,
                        'code' => $schedule->subject->code
                    ],
                    'section' => [
                        'id' => $schedule->section->id,
                        'name' => $schedule->section->name,
                        'year_level' => optional($schedule->section->yearLevel)->name
                    ],
                    'time' => [
                        'start' => $schedule->start_time,
                        'end' => $schedule->end_time,
                        'duration' => Carbon::parse($schedule->start_time)->diffInMinutes(Carbon::parse($schedule->end_time)) . ' minutes'
                    ],
                    'room' => $schedule->room,
                    'status' => $exception ? $exception->type : 'regular',
                    'exception' => $exception ? [
                        'type' => $exception->type,
                        'reason' => $exception->reason,
                        'new_time' => $exception->new_start_time ? [
                            'start' => $exception->new_start_time,
                            'end' => $exception->new_end_time
                        ] : null,
                        'new_room' => $exception->new_room
                    ] : null
                ];
            });

            $weeklySchedule[$day] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_name' => $day,
                'is_class_day' => $calendarEvent ? $calendarEvent->is_class_day : true,
                'calendar_event' => $calendarEvent ? [
                    'type' => $calendarEvent->type,
                    'title' => $calendarEvent->title,
                    'description' => $calendarEvent->description
                ] : null,
                'classes' => $daySchedules->values()
            ];
        }

        return $weeklySchedule;
    }

    private function errorResponse($message, $statusCode = 500, $error = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($error) {
            $response['error'] = $error;
        }

        return response()->json($response, $statusCode);
    }
}
