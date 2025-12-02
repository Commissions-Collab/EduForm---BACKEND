<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AttendanceHelper;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Quarter;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\Section;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

            $academicYearId = $request->get('academic_year_id');
            $sectionId = $request->get('section_id');
            $quarterId = $request->get('quarter_id');

            // Get current academic year or allow override via request
            $academicYear = $this->getCurrentAcademicYear($academicYearId);

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

            // Build query for teacher's schedules
            $schedulesQuery = Schedule::with([
                'subject:id,name,code',
                'section:id,name',
                'section.yearLevel:id,name',
                'scheduleExceptions' => function ($query) use ($weekStart, $weekEnd) {
                    $query->whereBetween('date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
                }
            ])
                ->where('teacher_id', $teacher->id)
                ->where('academic_year_id', $academicYear->id)
                ->where('is_active', true);

            // Apply section filter if provided
            if ($sectionId) {
                $schedulesQuery->where('section_id', $sectionId);
            }

            $schedules = $schedulesQuery
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
                    'academic_year' => [
                        'id' => $academicYear->id,
                        'name' => $academicYear->name
                    ],
                    'week_period' => [
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d'),
                        'week_number' => $weekStart->weekOfYear
                    ],
                    'schedule' => $weeklySchedule,
                    'calendar_events' => $calendarEvents,
                    // Flatten schedules for easier frontend consumption
                    'schedules' => $schedules->map(function ($schedule) {
                        return [
                            'id' => $schedule->id,
                            'subject' => $schedule->subject,
                            'section' => $schedule->section,
                            'day_of_week' => $schedule->day_of_week,
                            'time_start' => Carbon::parse($schedule->start_time)->format('H:i'),
                            'time_end' => Carbon::parse($schedule->end_time)->format('H:i'),
                            'room' => $schedule->room
                        ];
                    })
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

    /**
     * Get schedule attendance data - new method for frontend compatibility
     */
    public function getScheduleAttendance(Request $request): JsonResponse
    {
        try {
            $scheduleId = $request->get('schedule_id');
            $date = $request->get('date', Carbon::now()->format('Y-m-d'));

            if (!$scheduleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule ID is required'
                ], 400);
            }

            $response = $this->getScheduleStudents($scheduleId, $request);
            $responseData = json_decode($response->getContent(), true);

            if ($responseData['success']) {
                // Reformat to match frontend expectations
                $students = collect($responseData['data']['students'])->map(function ($student) {
                    return [
                        'id' => $student['id'],
                        'name' => $student['full_name'],
                        'first_name' => $student['first_name'],
                        'last_name' => $student['last_name'],
                        'attendance_status' => $student['attendance']['status'] ?? 'Present',
                        'attendance_reason' => $student['attendance']['remarks'] ?? '',
                        'attendance' => $student['attendance']
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data' => [
                        'schedule' => $responseData['data']['schedule'],
                        'students' => $students,
                        'summary' => $responseData['data']['summary'],
                        'attendance_date' => $date
                    ]
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schedule attendance',
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
            'remarks' => 'nullable|string|max:500',
            'reason' => 'nullable|string|max:500' // Added reason field
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($request->schedule_id, $teacher->id);

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule not found or access denied'
                ], 404);
            }

            $academicYear = $this->getCurrentAcademicYear();
            $currentQuarter = $this->getCurrentQuarter($academicYear->id);

            // Handle date parameter (frontend sends 'date', backend expects 'attendance_date')
            $attendanceDate = $request->attendance_date ?? $request->date;

            // Prepare attendance data
            $attendanceData = [
                'student_id' => $request->student_id,
                'schedule_id' => $request->schedule_id,
                'attendance_date' => $attendanceDate,
                'academic_year_id' => $academicYear->id,
                'quarter_id' => $currentQuarter->id,
                'status' => strtolower($request->status),
                'time_in' => $request->time_in,
                'time_out' => $request->time_out,
                'remarks' => $request->remarks ?? $request->reason,
            ];

            // Apply tardiness rule if status is "late"
            $attendanceData = AttendanceHelper::applyTardinessRule($attendanceData);

            // Store tardiness metadata separately
            $tardinessInfo = null;
            if (isset($attendanceData['tardiness_conversion'])) {
                $tardinessInfo = [
                    'converted' => $attendanceData['tardiness_conversion'],
                    'message' => $attendanceData['tardiness_message']
                ];
                unset($attendanceData['tardiness_conversion']);
                unset($attendanceData['tardiness_message']);
            }

            // Add recording metadata
            $attendanceData['recorded_by'] = Auth::id();
            $attendanceData['recorded_at'] = now();

            // Update or create attendance record
            $attendance = Attendance::updateOrCreate(
                [
                    'student_id' => $request->student_id,
                    'schedule_id' => $request->schedule_id,
                    'attendance_date' => $attendanceDate
                ],
                $attendanceData
            );

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Attendance updated successfully',
                'data' => [
                    'attendance' => $attendance
                ]
            ];

            // Add tardiness warning if conversion happened
            if ($tardinessInfo && $tardinessInfo['converted']) {
                $response['warning'] = $tardinessInfo['message'];
                $response['tardiness_conversion'] = true;
            }

            return response()->json($response);
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
            'attendances.*.remarks' => 'nullable|string|max:500',
            'attendances.*.reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($request->schedule_id, $teacher->id);

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule not found or access denied'
                ], 404);
            }

            $academicYear = $this->getCurrentAcademicYear();
            $currentQuarter = $this->getCurrentQuarter($academicYear->id);

            $updatedAttendances = [];
            $tardinessConversions = [];

            DB::beginTransaction();

            foreach ($request->attendances as $attendanceData) {
                // Prepare attendance data
                $data = [
                    'student_id' => $attendanceData['student_id'],
                    'schedule_id' => $request->schedule_id,
                    'attendance_date' => $request->attendance_date,
                    'academic_year_id' => $academicYear->id,
                    'quarter_id' => $currentQuarter->id,
                    'status' => strtolower($attendanceData['status']),
                    'time_in' => $attendanceData['time_in'] ?? null,
                    'time_out' => $attendanceData['time_out'] ?? null,
                    'remarks' => $attendanceData['remarks'] ?? $attendanceData['reason'] ?? null,
                ];

                // Apply tardiness rule
                $data = AttendanceHelper::applyTardinessRule($data);

                // Track conversions
                if (isset($data['tardiness_conversion']) && $data['tardiness_conversion']) {
                    $tardinessConversions[] = [
                        'student_id' => $attendanceData['student_id'],
                        'message' => $data['tardiness_message']
                    ];
                    unset($data['tardiness_conversion']);
                    unset($data['tardiness_message']);
                }

                $data['recorded_by'] = Auth::id();
                $data['recorded_at'] = now();

                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $attendanceData['student_id'],
                        'schedule_id' => $request->schedule_id,
                        'attendance_date' => $request->attendance_date
                    ],
                    $data
                );

                $updatedAttendances[] = $attendance;
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Bulk attendance updated successfully',
                'data' => [
                    'updated_count' => count($updatedAttendances),
                    'attendances' => $updatedAttendances
                ]
            ];

            // Add tardiness warnings if any conversions happened
            if (!empty($tardinessConversions)) {
                $response['tardiness_conversions'] = $tardinessConversions;
                $response['warning'] = count($tardinessConversions) . ' student(s) reached 5th late and were marked as ABSENT';
            }

            return response()->json($response);
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
            'attendance_date' => 'nullable|date',
            'date' => 'nullable|date', // Alternative field name
            'status' => 'required|in:present,absent,late,excused',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'remarks' => 'nullable|string|max:500',
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($request->schedule_id, $teacher->id);

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule not found or access denied'
                ], 404);
            }

            $students = Student::with(['enrollments'])->whereHas('enrollments', function ($query) use ($schedule) {
                $query->where('enrollment_status', 'enrolled')
                    ->where('section_id', $schedule->section_id);
            })
                ->get();

            $academicYear = $this->getCurrentAcademicYear();
            $currentQuarter = $this->getCurrentQuarter($academicYear->id);

            $updatedAttendances = [];
            $tardinessConversions = [];
            $attendanceDate = $request->attendance_date ?? $request->date ?? Carbon::now()->format('Y-m-d');

            DB::beginTransaction();

            foreach ($students as $student) {
                // Prepare attendance data
                $data = [
                    'student_id' => $student->id,
                    'schedule_id' => $request->schedule_id,
                    'attendance_date' => $attendanceDate,
                    'academic_year_id' => $academicYear->id,
                    'quarter_id' => $currentQuarter->id,
                    'status' => strtolower($request->status),
                    'time_in' => $request->time_in,
                    'time_out' => $request->time_out,
                    'remarks' => $request->remarks ?? $request->reason,
                ];

                // Apply tardiness rule
                $data = AttendanceHelper::applyTardinessRule($data);

                // Track conversions
                if (isset($data['tardiness_conversion']) && $data['tardiness_conversion']) {
                    $tardinessConversions[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->first_name . ' ' . $student->last_name,
                        'message' => $data['tardiness_message']
                    ];
                    unset($data['tardiness_conversion']);
                    unset($data['tardiness_message']);
                }

                $data['recorded_by'] = Auth::id();
                $data['recorded_at'] = now();

                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'schedule_id' => $request->schedule_id,
                        'attendance_date' => $attendanceDate
                    ],
                    $data
                );

                $updatedAttendances[] = $attendance;
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'All students attendance updated successfully',
                'data' => [
                    'updated_count' => count($updatedAttendances),
                    'total_students' => $students->count(),
                    'status_applied' => $request->status,
                    'attendances' => $updatedAttendances
                ]
            ];

            // Add tardiness warnings if any conversions happened
            if (!empty($tardinessConversions)) {
                $response['tardiness_conversions'] = $tardinessConversions;
                $response['warning'] = count($tardinessConversions) . ' student(s) reached 5th late and were marked as ABSENT';
            }

            return response()->json($response);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update all students attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAttendanceHistory($scheduleId, Request $request): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($scheduleId, $teacher->id);

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule not found or access denied'
                ], 404);
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStudentAttendanceHistory($studentId, $scheduleId, Request $request): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;

            // Verify schedule belongs to teacher
            $schedule = AttendanceHelper::verifyScheduleAccess($scheduleId, $teacher->id);

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule not found or access denied'
                ], 404);
            }

            // Verify student belongs to the section
            $student = AttendanceHelper::verifyStudentAccess($studentId, $schedule->section_id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found or not enrolled in this section'
                ], 404);
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student attendance history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods remain the same
    private function getCurrentAcademicYear($academicYearId = null)
    {
        try {
            if ($academicYearId) {
                return AcademicYear::findOrFail($academicYearId);
            }

            // Try with boolean first
            $academicYear = AcademicYear::where('is_current', true)->first();

            // If not found, try with integer 1
            if (!$academicYear) {
                $academicYear = AcademicYear::where('is_current', 1)->first();
            }

            // If still not found, get the most recent one
            if (!$academicYear) {
                $academicYear = AcademicYear::orderBy('id', 'desc')->first();
            }

            if (!$academicYear) {
                throw new \Exception('No academic year found in database');
            }

            return $academicYear;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function getCurrentQuarter($academicYearId = null)
    {
        $today = Carbon::today();

        if (!$academicYearId) {
            // Try with boolean first
            $currentYear = AcademicYear::where('is_current', true)->first();

            // If not found, try with integer 1
            if (!$currentYear) {
                $currentYear = AcademicYear::where('is_current', 1)->first();
            }

            // If still not found, get the most recent one
            if (!$currentYear) {
                $currentYear = AcademicYear::orderBy('id', 'desc')->first();
            }

            if (!$currentYear) {
                return null;
            }
            $academicYearId = $currentYear->id;
        }

        $currentQuarter = Quarter::where('academic_year_id', $academicYearId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

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

    private function formatWeeklySchedule($schedules, Carbon $weekStart, $calendarEvents)
    {
        $days = [];
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Define weekdays explicitly
        $weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        foreach ($weekDays as $dayName) {
            $date = $weekStart->copy()->next($dayName);
            if ($dayName === 'Monday') {
                $date = $weekStart->copy();
            }

            // Filter schedules by weekday
            $daySchedules = $schedules->filter(function ($s) use ($dayName) {
                return $s->day_of_week === $dayName;
            });

            // Match calendar event for that date
            $calendarEvent = collect($calendarEvents)->firstWhere('date', $date->format('Y-m-d'));

            $days[$dayName] = [
                'date' => $date->format('Y-m-d'),
                'classes' => $daySchedules->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'subject' => $s->subject,
                        'section' => $s->section,
                        'time_start' => Carbon::parse($s->time_start)->format('H:i'),
                        'time_end' => Carbon::parse($s->time_end)->format('H:i'),
                        'room' => $s->room,
                    ];
                })->values(),
                'calendar_event' => $calendarEvent,
                'is_class_day' => $calendarEvent ? (bool) $calendarEvent->is_class_day : true
            ];
        }

        return $days;
    }

    /**
     * Export SF2 (School Form 2) Daily Attendance Report as Excel
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportSF2Excel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'section_id' => 'required|exists:sections,id',
                'academic_year_id' => 'required|exists:academic_years,id',
                'month' => 'required|date_format:Y-m',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $teacher = Auth::user()->teacher;
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found'
                ], 404);
            }

            $sectionId = $request->get('section_id');
            $academicYearId = $request->get('academic_year_id');
            $month = $request->get('month');

            // Parse month
            $date = Carbon::createFromFormat('Y-m', $month);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();
            $monthName = $date->format('F');
            $year = $date->format('Y');

            // Get section and academic year
            $section = Section::with(['yearLevel', 'academicYear'])->findOrFail($sectionId);
            $academicYear = AcademicYear::findOrFail($academicYearId);

            // Verify teacher has access to this section
            // Teacher can access if they are a section advisor OR have schedules for this section
            $isSectionAdvisor = DB::table('section_advisors')
                ->where('section_id', $sectionId)
                ->where('teacher_id', $teacher->id)
                ->where('academic_year_id', $academicYearId)
                ->exists();

            $hasSchedule = Schedule::where('section_id', $sectionId)
                ->where('teacher_id', $teacher->id)
                ->where('academic_year_id', $academicYearId)
                ->where('is_active', true)
                ->exists();

            if (!$isSectionAdvisor && !$hasSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this section'
                ], 403);
            }

            // Get enrolled students in section
            $students = Student::whereHas('enrollments', function ($query) use ($sectionId, $academicYearId) {
                $query->where('section_id', $sectionId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('enrollment_status', 'enrolled');
            })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            // Get all attendance records for the month
            $attendanceRecords = Attendance::whereIn('student_id', $students->pluck('id'))
                ->whereBetween('attendance_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                ->where('academic_year_id', $academicYearId)
                ->get();

            // Group by student_id then by date
            // If multiple records exist for same day, prioritize: absent > late > present
            $attendances = [];
            foreach ($attendanceRecords as $record) {
                $studentId = $record->student_id;
                $dateKey = $record->attendance_date->format('Y-m-d');

                if (!isset($attendances[$studentId][$dateKey])) {
                    $attendances[$studentId][$dateKey] = $record;
                } else {
                    // If already exists, prioritize worst status (absent > late > present)
                    $existingStatus = $attendances[$studentId][$dateKey]->status;
                    $newStatus = $record->status;

                    $priority = ['absent' => 3, 'late' => 2, 'present' => 1, 'excused' => 1];
                    $existingPriority = $priority[$existingStatus] ?? 0;
                    $newPriority = $priority[$newStatus] ?? 0;

                    if ($newPriority > $existingPriority) {
                        $attendances[$studentId][$dateKey] = $record;
                    }
                }
            }

            // Get all school days in the month (Monday to Friday)
            $schoolDays = [];
            $currentDate = $startOfMonth->copy();
            while ($currentDate->lte($endOfMonth)) {
                $dayOfWeek = $currentDate->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday to Friday
                    $schoolDays[] = $currentDate->copy();
                }
                $currentDate->addDay();
            }

            // Group school days by week
            $weeks = [];
            $currentWeek = [];
            foreach ($schoolDays as $day) {
                if (empty($currentWeek) || $day->dayOfWeek == 1) {
                    if (!empty($currentWeek)) {
                        $weeks[] = $currentWeek;
                    }
                    $currentWeek = [];
                }
                $currentWeek[] = $day;
            }
            if (!empty($currentWeek)) {
                $weeks[] = $currentWeek;
            }

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SF2 Daily Attendance');

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(35); // Learner's Name
            $col = 'B';
            foreach ($weeks as $week) {
                for ($i = 0; $i < 5; $i++) {
                    $sheet->getColumnDimension($col)->setWidth(8);
                    $col++;
                }
            }
            $sheet->getColumnDimension($col)->setWidth(10); // ABSENT
            $col++;
            $sheet->getColumnDimension($col)->setWidth(10); // TARDY
            $col++;
            $sheet->getColumnDimension($col)->setWidth(30); // REMARKS

            // Header Section
            $row = 1;
            $sheet->setCellValue('A' . $row, 'School Form 2 (SF2) Daily Attendance Report of Learners');
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $sheet->setCellValue('A' . $row, '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)');
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row += 2;
            // School Information
            $sheet->setCellValue('A' . $row, 'School ID:');
            $sheet->setCellValue('B' . $row, '308041');
            $sheet->setCellValue('D' . $row, 'Region:');
            $sheet->setCellValue('E' . $row, 'IV-A');

            $row++;
            $sheet->setCellValue('A' . $row, 'School Name:');
            $sheet->setCellValue('B' . $row, 'Castañas National Highschool');
            $sheet->setCellValue('D' . $row, 'Division:');
            $sheet->setCellValue('E' . $row, 'Quezon Province');

            $row++;
            $sheet->setCellValue('A' . $row, 'District:');
            $sheet->setCellValue('B' . $row, 'Sariaya East');
            $sheet->setCellValue('D' . $row, 'School Year:');
            $sheet->setCellValue('E' . $row, $academicYear->name);

            $row++;
            $sheet->setCellValue('A' . $row, 'Report for the Month of:');
            $sheet->setCellValue('B' . $row, $monthName . ' ' . $year);
            $sheet->setCellValue('D' . $row, 'Grade Level:');
            $sheet->setCellValue('E' . $row, $section->yearLevel->name ?? 'N/A');

            $row++;
            $sheet->setCellValue('A' . $row, 'Section:');
            $sheet->setCellValue('B' . $row, $section->name);

            $row += 2;

            // Table Header
            $headerRow = $row;
            $sheet->setCellValue('A' . $row, 'LEARNER\'S NAME (Last Name, First Name, Middle Name)');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            // Add week headers
            $col = 'B';
            $weekNum = 1;
            foreach ($weeks as $week) {
                $startCol = $col;
                for ($i = 0; $i < 5; $i++) {
                    $dayLabel = ['M', 'T', 'W', 'TH', 'F'][$i];
                    $sheet->setCellValue($col . $row, $dayLabel);
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('D9E1F2');
                    $col++;
                }
                $weekNum++;
            }

            // ABSENT and TARDY columns
            $absentCol = $col;
            $sheet->setCellValue($col . $row, 'ABSENT');
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');
            $col++;

            $tardyCol = $col;
            $sheet->setCellValue($col . $row, 'TARDY');
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');
            $col++;

            $remarksCol = $col;
            $sheet->setCellValue($col . $row, 'REMARKS');
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            // Date row (below header)
            $row++;
            $dateRow = $row;
            $sheet->setCellValue('A' . $row, '1st row for date');
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(9);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $col = 'B';
            foreach ($weeks as $week) {
                foreach ($week as $day) {
                    $sheet->setCellValue($col . $row, $day->format('d'));
                    $sheet->getStyle($col . $row)->getFont()->setSize(9);
                    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $col++;
                }
                // Fill remaining days in week if less than 5
                while (substr($col, -1) != 'F' && Coordinate::columnIndexFromString($col) <= Coordinate::columnIndexFromString('F')) {
                    $col++;
                }
            }

            $row++;

            // Student rows
            $maleTotal = 0;
            $femaleTotal = 0;
            $dailyTotals = [];

            foreach ($students as $student) {
                $studentRow = $row;
                $fullName = trim($student->last_name . ', ' . $student->first_name . ' ' . ($student->middle_name ?? ''));
                $sheet->setCellValue('A' . $row, $fullName);
                $sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $absentCount = 0;
                $tardyCount = 0;

                $col = 'B';
                foreach ($weeks as $week) {
                    foreach ($week as $day) {
                        $dayKey = $day->format('Y-m-d');
                        
                        // Check if date is in the future (strictly greater than today)
                        if ($day->isFuture()) {
                            $sheet->setCellValue($col . $row, '');
                        } else {
                            $attendance = $attendances[$student->id][$dayKey] ?? null;

                            if ($attendance) {
                                // Get status from attendance record
                                $status = is_object($attendance) ? ($attendance->status ?? 'present') : 'present';
                                if ($status === 'absent') {
                                    $sheet->setCellValue($col . $row, 'X');
                                    $absentCount++;
                                } elseif ($status === 'late') {
                                    // Half-shaded for late (upper half)
                                    $sheet->setCellValue($col . $row, 'T');
                                    $tardyCount++;
                                } else {
                                    // Present - show checkmark
                                    $sheet->setCellValue($col . $row, '✓');
                                }
                            } else {
                                $sheet->setCellValue($col . $row, '');
                            }
                        }

                        $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $col++;
                    }
                }

                // ABSENT total
                $sheet->setCellValue($absentCol . $row, $absentCount);
                $sheet->getStyle($absentCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // TARDY total
                $sheet->setCellValue($tardyCol . $row, $tardyCount);
                $sheet->getStyle($tardyCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // REMARKS (empty for now, can be filled from attendance remarks)
                $sheet->setCellValue($remarksCol . $row, '');

                // Track gender for summary
                if ($student->gender === 'male') {
                    $maleTotal++;
                } else {
                    $femaleTotal++;
                }

                $row++;
            }

            // Summary rows
            $summaryStartRow = $row;
            $row++;
            $sheet->setCellValue('A' . $row, 'MALE | TOTAL Per Day');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');

            $row++;
            $sheet->setCellValue('A' . $row, 'FEMALE | TOTAL Per Day');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');

            $row++;
            $sheet->setCellValue('A' . $row, 'Combined TOTAL PER DAY');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');

            // Apply borders to all data cells
            $lastDataRow = $row;
            $lastCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($remarksCol));
            $range = 'A' . $headerRow . ':' . $lastCol . $lastDataRow;
            $sheet->getStyle($range)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);

            // Guidelines section (on the left side, below the table)
            $guidelinesRow = $lastDataRow + 3;
            $sheet->setCellValue('A' . $guidelinesRow, 'GUIDELINES:');
            $sheet->getStyle('A' . $guidelinesRow)->getFont()->setBold(true)->setSize(11);

            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '1. The attendance shall be accomplished daily. Refer to the codes for checking learners\' attendance.');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '2. Dates shall be written in the columns after Learner\'s Name.');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '3. To compute the following:');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '   a. Percentage of Enrolment = (Registered Learners as of end of the month / Enrolment as of 1st Friday of the school year) x 100');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '   b. Average Daily Attendance = Total Daily Attendance / Number of School Days in reporting month');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '   c. Percentage of Attendance for the month = (Average daily attendance / Registered Learners as of end of the month) x 100');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '4. Every end of the month, the class adviser will submit this form to the office of the principal for recording of summary table into School Form 4.');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '5. The adviser will provide necessary interventions including but not limited to home visitation to learner/s who were absent for 5 consecutive days.');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '6. Attendance performance of learners will be reflected in Form 137 and Form 138 every grading period.');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '* Beginning of School Year cut-off report is every 1st Friday of the School Year');

            // Codes section
            $codesRow = $guidelinesRow + 2;
            $sheet->setCellValue('A' . $codesRow, '1. CODES FOR CHECKING ATTENDANCE:');
            $sheet->getStyle('A' . $codesRow)->getFont()->setBold(true)->setSize(11);
            $codesRow++;
            $sheet->setCellValue('A' . $codesRow, '(✓)-Present; (X)- Absent; Tardy (half shaded= Upper for Late Commer, Lower for Cutting Classes)');
            $codesRow += 2;
            $sheet->setCellValue('A' . $codesRow, '2. REASONS/CAUSES FOR DROPPING OUT:');
            $sheet->getStyle('A' . $codesRow)->getFont()->setBold(true)->setSize(11);
            $codesRow++;
            $sheet->setCellValue('A' . $codesRow, 'a. Domestic-Related Factors: (1. Had to take care of siblings, 2. Early marriage/pregnancy, 3. Parents\' attitude toward schooling, 4. Family problems)');
            $codesRow++;
            $sheet->setCellValue('A' . $codesRow, 'b. Individual-Related Factors: (1. Illness, 2. Overage, 3. Death, 4. Drug Abuse, 5. Poor academic performance, 6. Lack of interest/Distractions, 7. Hunger/Malnutrition)');
            $codesRow++;
            $sheet->setCellValue('A' . $codesRow, 'c. School-Related Factors: (1. Teacher Factor, 2. Physical condition of classroom, 3. Peer influence)');
            $codesRow++;
            $sheet->setCellValue('A' . $codesRow, 'd. Geographic/Environmental: (1. Distance between home and school, 2. Armed conflict, 3. Calamities/Disasters)');
            $codesRow++;
            $sheet->setCellValue('A' . $codesRow, 'e. Financial-Related: (1. Child labor, work)');
            $codesRow++;
            $sheet->setCellValue('A' . $codesRow, 'f. Others (Specify)');

            // Summary table (on the right side)
            $summaryTableRow = $lastDataRow + 3;
            $summaryTableCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($remarksCol) + 2);
            $sheet->setCellValue($summaryTableCol . $summaryTableRow, 'Month: ' . $monthName);
            $summaryTableRow++;
            $sheet->setCellValue($summaryTableCol . $summaryTableRow, 'No. of Days of Classes: ' . count($schoolDays));
            $summaryTableRow += 2;

            // Summary table headers
            $summaryHeaders = ['M', 'F', 'TOTAL'];
            $summaryStartCol = $summaryTableCol;
            foreach ($summaryHeaders as $header) {
                $sheet->setCellValue($summaryTableCol . $summaryTableRow, $header);
                $sheet->getStyle($summaryTableCol . $summaryTableRow)->getFont()->setBold(true);
                $sheet->getStyle($summaryTableCol . $summaryTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $summaryTableCol++;
            }
            $summaryTableRow++;

            // Summary data
            $enrollment = $students->count();
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, '*Enrolment as of (1st Friday of June)');
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Late Enrollment during the month (beyond cut-off)');
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Registered Learners as of end of the month');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryStartCol) + 2) . $summaryTableRow, $enrollment);
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Percentage of Enrolment as of end of the month');
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Average Daily Attendance');
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Percentage of Attendance for the month');
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Number of students absent for 5 consecutive days:');
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Drop out');
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Transferred out');
            $summaryTableRow++;
            $sheet->setCellValue($summaryStartCol . $summaryTableRow, 'Transferred in');

            // Certification
            $certRow = $summaryTableRow + 3;
            $sheet->setCellValue($summaryStartCol . $certRow, 'I certify that this is a true and correct report.');
            $certRow += 2;
            $sheet->setCellValue($summaryStartCol . $certRow, '(Signature of Teacher over Printed Name)');
            $certRow += 2;
            $sheet->setCellValue($summaryStartCol . $certRow, 'Attested by:');
            $certRow++;
            $sheet->setCellValue($summaryStartCol . $certRow, '(Signature of School Head over Printed Name)');

            // Footer
            $footerRow = $certRow + 2;
            $sheet->setCellValue('A' . $footerRow, 'School Form 2: Page ___ of ___');

            // Generate Excel file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'SF2_Daily_Attendance_' . $section->name . '_' . $monthName . '_' . $year . '.xlsx';

            ob_start();
            $writer->save('php://output');
            $content = ob_get_contents();
            ob_end_clean();

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            Log::error('SF2 Excel Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export SF2 Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tardiness statistics for a student
     * 
     * @param int $studentId
     * @param Request $request
     * @return JsonResponse
     */
    public function getStudentTardinessStats($studentId, Request $request): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;
            $academicYear = $this->getCurrentAcademicYear($request->get('academic_year_id'));
            $quarterId = $request->get('quarter_id');

            // Verify student belongs to one of teacher's sections
            $student = Student::whereHas('enrollments.section.schedules', function ($query) use ($teacher, $academicYear) {
                $query->where('teacher_id', $teacher->id)
                    ->where('academic_year_id', $academicYear->id);
            })->find($studentId);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found or access denied'
                ], 404);
            }

            $stats = AttendanceHelper::getTardinessStats(
                $studentId,
                $academicYear->id,
                $quarterId
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->first_name . ' ' . $student->last_name
                    ],
                    'academic_year' => [
                        'id' => $academicYear->id,
                        'name' => $academicYear->name
                    ],
                    'tardiness_stats' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tardiness statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monthly class schedule for the authenticated teacher
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getMonthlySchedule(Request $request): JsonResponse
    {
        try {
            $teacher = Auth::user()->teacher;
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found'
                ], 404);
            }

            $academicYearId = $request->get('academic_year_id');
            $sectionId = $request->get('section_id');
            $quarterId = $request->get('quarter_id'); // Get quarter_id from request

            // Get current academic year or allow override via request
            $academicYear = $this->getCurrentAcademicYear($academicYearId);

            if (!$academicYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic year not found',
                ], 404);
            }

            // Get month dates (default to current month or specified month)
            $monthStart = $request->get('month_start')
                ? Carbon::parse($request->get('month_start'))->startOfMonth()
                : Carbon::now()->startOfMonth();

            $monthEnd = $monthStart->copy()->endOfMonth();

            // Build query for teacher's schedules
            $schedulesQuery = Schedule::with([
                'subject:id,name,code',
                'section:id,name',
                'section.yearLevel:id,name',
                'scheduleExceptions' => function ($query) use ($monthStart, $monthEnd) {
                    $query->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
                }
            ])
                ->where('teacher_id', $teacher->id)
                ->where('academic_year_id', $academicYear->id)
                ->where('is_active', true);

            // Apply section filter if provided
            if ($sectionId) {
                $schedulesQuery->where('section_id', $sectionId);
            }

            // **FIX: Apply quarter filter if provided**
            if ($quarterId) {
                $schedulesQuery->where('quarter_id', $quarterId);
            }

            $schedules = $schedulesQuery
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            // Get academic calendar for the month (holidays, special events)
            $calendarEvents = $this->getMonthCalendarEvents($academicYear->id, $monthStart, $monthEnd);

            // Format schedule by dates
            $monthlySchedule = $this->formatMonthlySchedule($schedules, $monthStart, $monthEnd, $calendarEvents);

            return response()->json([
                'success' => true,
                'data' => [
                    'academic_year' => [
                        'id' => $academicYear->id,
                        'name' => $academicYear->name
                    ],
                    'quarter' => $quarterId ? [
                        'id' => $quarterId,
                    ] : null,
                    'month_period' => [
                        'start' => $monthStart->format('Y-m-d'),
                        'end' => $monthEnd->format('Y-m-d'),
                        'month' => $monthStart->format('F Y')
                    ],
                    'schedule' => $monthlySchedule,
                    'calendar_events' => $calendarEvents,
                    // Flatten schedules for easier frontend consumption
                    'schedules' => $schedules->map(function ($schedule) {
                        return [
                            'id' => $schedule->id,
                            'subject' => $schedule->subject,
                            'section' => $schedule->section,
                            'day_of_week' => $schedule->day_of_week,
                            'time_start' => Carbon::parse($schedule->start_time)->format('H:i'),
                            'time_end' => Carbon::parse($schedule->end_time)->format('H:i'),
                            'room' => $schedule->room
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch monthly schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function formatMonthlySchedule($schedules, Carbon $monthStart, Carbon $monthEnd, $calendarEvents)
    {
        $formattedSchedule = [];
        $currentDate = $monthStart->copy();

        // Group schedules by day of week for easy lookup
        $schedulesByDay = $schedules->groupBy('day_of_week');

        // Iterate through each day of the month
        while ($currentDate->lte($monthEnd)) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->format('l'); // Full day name (Monday, Tuesday, etc.)

            // Get schedules for this day of the week
            $daySchedules = $schedulesByDay->get($dayOfWeek, collect());

            // Check for calendar event on this date
            $calendarEvent = $calendarEvents->firstWhere('date', $dateKey);

            // Map schedules to include exceptions
            $schedulesWithExceptions = $daySchedules->map(function ($schedule) use ($dateKey) {
                $exception = $schedule->scheduleExceptions->firstWhere('date', $dateKey);

                $scheduleData = [
                    'id' => $schedule->id,
                    'subject' => $schedule->subject,
                    'section' => $schedule->section,
                    'time_start' => Carbon::parse($schedule->start_time)->format('H:i'),
                    'time_end' => Carbon::parse($schedule->end_time)->format('H:i'),
                    'room' => $schedule->room,
                    'status' => $exception ? $exception->type : 'scheduled'
                ];

                if ($exception) {
                    $scheduleData['exception'] = [
                        'type' => $exception->type,
                        'reason' => $exception->reason,
                        'new_time' => $exception->type === 'rescheduled' ? [
                            'start' => $exception->new_start_time,
                            'end' => $exception->new_end_time
                        ] : null,
                        'new_room' => $exception->new_room
                    ];
                }

                return $scheduleData;
            });

            // Determine if it's a class day
            $isClassDay = true;
            if ($calendarEvent && isset($calendarEvent->is_class_day)) {
                $isClassDay = (bool) $calendarEvent->is_class_day;
            }

            // DEBUG: Log weekend check
            if ($currentDate->day <= 7) { // Only log first week
                \Log::info("Weekend Check", [
                    'date' => $dateKey,
                    'dayOfWeek' => $dayOfWeek,
                    'dayOfWeekNum' => $currentDate->dayOfWeek,
                    'isWeekend' => $currentDate->isWeekend(),
                    'isClassDay_before' => $isClassDay
                ]);
            }

            if ($currentDate->isWeekend()) {
                $isClassDay = false;
            }

            // DEBUG: Log final result
            if ($currentDate->day <= 7) { // Only log first week
                \Log::info("Final isClassDay", [
                    'date' => $dateKey,
                    'isClassDay' => $isClassDay
                ]);
            }

            $formattedSchedule[$dateKey] = [
                'date' => $dateKey,
                'day_of_week' => $dayOfWeek,
                'classes' => $schedulesWithExceptions->values()->all(),
                'calendar_event' => $calendarEvent ? [
                    'id' => $calendarEvent->id,
                    'title' => $calendarEvent->title,
                    'description' => $calendarEvent->description ?? null,
                    'is_class_day' => (bool) ($calendarEvent->is_class_day ?? true)
                ] : null,
                'is_class_day' => $isClassDay
            ];

            $currentDate->addDay();
        }

        return $formattedSchedule;
    }

    /**
     * Get calendar events for a month
     * 
     * @param int $academicYearId
     * @param Carbon $monthStart
     * @param Carbon $monthEnd
     * @return \Illuminate\Support\Collection
     */
    private function getMonthCalendarEvents($academicYearId, Carbon $monthStart, Carbon $monthEnd)
    {
        return DB::table('academic_calendars')
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->get();
    }
}
