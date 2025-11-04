<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class MonthlyAttendanceController extends Controller
{
    /**
     * Get monthly attendance summary - Super Admin has full access to all sections
     * Uses the same logic as teacher controller but without advisor restrictions
     */
    public function getMonthlyAttendanceSummary($sectionId, Request $request)
    {
        try {
            // Validate section exists
            $section = Section::with(['yearLevel', 'students'])->findOrFail($sectionId);
            
            $academicYear = $this->getCurrentAcademicYear($request->get('academic_year_id'));

            // Get month and year from request (default to current month)
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            // Get date range for the month
            $startDate = Carbon::create($year, $month, 1);
            $endDate = $startDate->copy()->endOfMonth();

            // Get all students in the section
            $students = $section->students;

            // Get all schedules for this section
            $schedules = $this->getSectionSchedules($sectionId, $academicYear->id);

            // Get attendance data for the month
            $attendanceData = $this->getMonthlyAttendanceData($sectionId, $academicYear->id, $startDate, $endDate);

            // Generate calendar days for the month
            $calendarDays = $this->generateCalendarDays($startDate, $endDate);

            // Allow caller to choose counting mode:
            // - present_mode=strict (default): present only if attended all scheduled subjects
            // - present_mode=lenient: present if attended at least one scheduled subject (treat half-day as present)
            $presentMode = $request->get('present_mode', 'strict');

            // Process daily attendance summary for each student
            $studentSummaries = $this->processStudentDailyAttendance($students, $schedules, $attendanceData, $calendarDays, $presentMode);

            return response()->json([
                'success' => true,
                'data' => [
                    'section' => [
                        'id' => $section->id,
                        'name' => $section->name,
                        'year_level' => $section->yearLevel->name
                    ],
                    'academic_year' => [
                        'id' => $academicYear->id,
                        'name' => $academicYear->name
                    ],
                    'period' => [
                        'month' => $month,
                        'year' => $year,
                        'month_name' => $startDate->format('F Y'),
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d')
                    ],
                    'calendar_days' => $calendarDays,
                    'students' => $studentSummaries,
                    'summary_legend' => [
                        'present' => 'Present all day (attended all subjects)',
                        'half_day' => 'Half day (missed at least one subject)',
                        'absent' => 'Absent (did not attend any subject)',
                        'no_class' => 'No scheduled classes'
                    ],
                    'class_statistics' => $this->calculateClassStatistics($studentSummaries)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('SuperAdminMonthlyAttendanceController error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'section_id' => $sectionId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch monthly attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getCurrentAcademicYear($academicYearId = null)
    {
        if ($academicYearId) {
            return AcademicYear::findOrFail($academicYearId);
        }

        return AcademicYear::where('is_current', true)->firstOrFail();
    }

    private function getSectionSchedules($sectionId, $academicYearId)
    {
        return Schedule::with(['subject'])
            ->where('section_id', $sectionId)
            ->where('academic_year_id', $academicYearId)
            ->get();
    }

    private function getMonthlyAttendanceData($sectionId, $academicYearId, $startDate, $endDate)
    {
        // Normalize the date range to include the full days and group results by
        // student id and the date portion (Y-m-d) of attendance_date. This avoids
        // mismatches when attendance_date includes time components.
        $attendances = Attendance::with(['student', 'schedule.subject'])
            ->whereHas('student.enrollments', function ($query) use ($sectionId, $academicYearId) {
                $query->where('section_id', $sectionId)
                    ->where('enrollment_status', 'enrolled')
                    ->where('academic_year_id', $academicYearId);
            })
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('attendance_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        // Group first by student_id, then by the date string (Y-m-d) for the attendance_date
        return $attendances->groupBy(function ($item) {
            return $item->student_id;
        })->map(function ($group) {
            return $group->groupBy(function ($att) {
                return Carbon::parse($att->attendance_date)->format('Y-m-d');
            });
        });
    }

    private function generateCalendarDays($startDate, $endDate): array
    {
        $days = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $days[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'day_name' => $current->format('D'),
                'is_weekend' => $current->isWeekend(),
                'is_holiday' => false
            ];
            $current->addDay();
        }

        return $days;
    }

    private function processStudentDailyAttendance($students, $schedules, $attendanceData, $calendarDays, $presentMode = 'strict'): array
    {
        $studentSummaries = [];

        foreach ($students as $student) {
            $dailyAttendance = [];
            $monthlySummary = [
                'present_days' => 0,
                'half_days' => 0,
                'absent_days' => 0,
                'no_class_days' => 0
            ];

            foreach ($calendarDays as $day) {
                $dateKey = $day['date'];
                $dayStatus = $this->calculateDayStatus(
                    $student->id,
                    $dateKey,
                    $schedules,
                    $attendanceData,
                    $day['is_weekend'] || $day['is_holiday'],
                    $presentMode
                );

                $dailyAttendance[] = [
                    'date' => $dateKey,
                    'day' => $day['day'],
                    'status' => $dayStatus,
                    'is_weekend' => $day['is_weekend'],
                    'is_holiday' => $day['is_holiday']
                ];

                // Map day status to the correct monthly summary key.
                // Avoid using string concatenation which produced keys like
                // "half_day_days" instead of the expected "half_days".
                switch ($dayStatus) {
                    case 'present':
                        $monthlySummary['present_days']++;
                        break;
                    case 'half_day':
                        $monthlySummary['half_days']++;
                        break;
                    case 'absent':
                        $monthlySummary['absent_days']++;
                        break;
                    case 'no_class':
                        $monthlySummary['no_class_days']++;
                        break;
                    default:
                        // unknown status - ignore
                        break;
                }
            }

            $studentSummaries[] = [
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'lrn' => $student->lrn,
                    'full_name' => trim($student->first_name . ' ' . $student->middle_name . ' ' . $student->last_name),
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'photo' => $student->photo
                ],
                'daily_attendance' => $dailyAttendance,
                'monthly_summary' => $monthlySummary,
                'attendance_rate' => $this->calculateAttendanceRate($monthlySummary)
            ];
        }

        return $studentSummaries;
    }

    private function calculateDayStatus($studentId, $date, $schedules, $attendanceData, $isNonSchoolDay, $presentMode = 'strict'): string
    {
        if ($isNonSchoolDay) {
            return 'no_class';
        }

        // attendanceData is a collection grouped by student_id -> Y-m-d -> [attendances]
        $studentAttendanceGroup = null;
        if ($attendanceData instanceof \Illuminate\Support\Collection) {
            $studentAttendanceGroup = $attendanceData->get($studentId);
        } elseif (is_array($attendanceData) && array_key_exists($studentId, $attendanceData)) {
            $studentAttendanceGroup = $attendanceData[$studentId];
        }

        $dayAttendance = collect();
        if ($studentAttendanceGroup instanceof \Illuminate\Support\Collection) {
            $dayAttendance = $studentAttendanceGroup->get($date, collect());
        } elseif (is_array($studentAttendanceGroup) && array_key_exists($date, $studentAttendanceGroup)) {
            $dayAttendance = collect($studentAttendanceGroup[$date]);
        }

        if ($dayAttendance->isEmpty()) {
            // If there are no attendance records for the day, we still need to
            // determine whether there were scheduled classes on that particular
            // weekday. If there are schedules for that date, the student is
            // considered absent; otherwise it's a no-class day.
            $weekdayName = Carbon::parse($date)->format('l'); // e.g. "Monday"
            $schedulesForDate = $schedules->filter(function ($s) use ($weekdayName) {
                return isset($s->day_of_week) && $s->day_of_week == $weekdayName;
            });

            return $schedulesForDate->isNotEmpty() ? 'absent' : 'no_class';
        }

        // Count only schedules that occur on this specific weekday (e.g. Monday)
        $weekdayName = Carbon::parse($date)->format('l');
        $schedulesForDate = $schedules->filter(function ($s) use ($weekdayName) {
            return isset($s->day_of_week) && $s->day_of_week == $weekdayName;
        });

        $totalSubjects = $schedulesForDate->count();

        // Use unique schedule_id counts to avoid double-counting multiple records
        // for the same schedule (if any).
        $attendedSubjects = $dayAttendance->whereIn('status', ['present', 'late'])
            ->pluck('schedule_id')
            ->unique()
            ->count();

        $recordedSubjects = $dayAttendance->pluck('schedule_id')->unique()->count();

        if ($totalSubjects === 0) {
            return 'no_class';
        }

        if ($attendedSubjects == $totalSubjects && $recordedSubjects == $totalSubjects) {
            return 'present';
        } elseif ($attendedSubjects > 0) {
            return 'half_day';
        } else {
            return 'absent';
        }
    }

    private function calculateAttendanceRate($summary): float
    {
        $totalSchoolDays = $summary['present_days'] + $summary['half_days'] + $summary['absent_days'];

        if ($totalSchoolDays == 0) {
            return 0;
        }

        $attendanceScore = $summary['present_days'] + ($summary['half_days'] * 0.5);

        return round(($attendanceScore / $totalSchoolDays) * 100, 2);
    }

    private function calculateClassStatistics($studentSummaries): array
    {
        if (empty($studentSummaries)) {
            return [
                'total_students' => 0,
                'average_attendance_rate' => 0,
                'highest_attendance' => 0,
                'lowest_attendance' => 0,
                'students_above_90' => 0,
                'students_below_75' => 0
            ];
        }

        $attendanceRates = array_column($studentSummaries, 'attendance_rate');
        $above90 = count(array_filter($attendanceRates, fn($rate) => $rate >= 90));
        $below75 = count(array_filter($attendanceRates, fn($rate) => $rate < 75));

        return [
            'total_students' => count($studentSummaries),
            'average_attendance_rate' => round(array_sum($attendanceRates) / count($attendanceRates), 2),
            'highest_attendance' => max($attendanceRates),
            'lowest_attendance' => min($attendanceRates),
            'students_above_90' => $above90,
            'students_below_75' => $below75
        ];
    }
}