<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Traits\AdvisorAccessTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonthlyAttendanceController extends Controller
{
    use AdvisorAccessTrait;

    /**
     * Get monthly attendance summary - Only accessible by section advisors
     */
    public function getMonthlyAttendanceSummary($sectionId, Request $request)
    {
        try {
            $teacher = Auth::user()->teacher;
            $academicYear = $this->getCurrentAcademicYear($request->get('academic_year_id'));

            // Check advisor access first
            $accessError = $this->requireAdvisorAccess($sectionId, $teacher->id, $academicYear->id);
            if ($accessError) {
                return $accessError;
            }

            // Get section advisor with full details
            $sectionAdvisor = $this->getSectionAdvisorWithDetails($sectionId, $teacher->id, $academicYear->id);
            $section = $sectionAdvisor->section;

            // Get month and year from request (default to current month)
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            // Get date range for the month
            $startDate = Carbon::create($year, $month, 1);
            $endDate = $startDate->copy()->endOfMonth();

            // Get all students in the section (already loaded via relationship)
            $students = $section->students;

            // Get all schedules for this section
            $schedules = $this->getSectionSchedules($sectionId, $academicYear->id);

            // Get attendance data for the month
            $attendanceData = $this->getMonthlyAttendanceData($sectionId, $academicYear->id, $startDate, $endDate);

            // Generate calendar days for the month
            $calendarDays = $this->generateCalendarDays($startDate, $endDate);

            // Process daily attendance summary for each student
            $studentSummaries = $this->processStudentDailyAttendance($students, $schedules, $attendanceData, $calendarDays);

            return response()->json([
                'success' => true,
                'data' => [
                    'advisor' => [
                        'id' => $sectionAdvisor->teacher->id,
                        'name' => trim($sectionAdvisor->teacher->first_name . ' ' . $sectionAdvisor->teacher->last_name),
                        'email' => $sectionAdvisor->teacher->user->email
                    ],
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
            return $this->errorResponse('Failed to fetch monthly attendance summary', 500, $e->getMessage());
        }
    }

    private function getCurrentAcademicYear($academicYearId = null)
    {
        if ($academicYearId) {
            return AcademicYear::findOrFail($academicYearId);
        }

        return AcademicYear::where('is_current', true)->firstOrFail();
    }

    /**
     * Get all schedules for the section
     */
    private function getSectionSchedules($sectionId, $academicYearId)
    {
        return Schedule::with(['subject'])
            ->where('section_id', $sectionId)
            ->where('academic_year_id', $academicYearId)
            ->get();
    }

    /**
     * Get attendance data for the month
     */
    private function getMonthlyAttendanceData($sectionId, $academicYearId, $startDate, $endDate)
    {
        return Attendance::with(['student', 'schedule.subject'])
            ->whereHas('student.enrollments', function ($query) use ($sectionId, $academicYearId) {
                $query->where('section_id', $sectionId)
                    ->where('enrollment_status', 'enrolled')
                    ->where('academic_year_id', $academicYearId);
            })
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->groupBy(['student_id', 'attendance_date']);
    }

    /**
     * Generate calendar days for the month
     */
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
                'is_holiday' => $this->isHoliday($current) // You can implement holiday checking
            ];
            $current->addDay();
        }

        return $days;
    }

    /**
     * Process daily attendance for each student
     */
    private function processStudentDailyAttendance($students, $schedules, $attendanceData, $calendarDays): array
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
                    $day['is_weekend'] || $day['is_holiday']
                );

                $dailyAttendance[] = [
                    'date' => $dateKey,
                    'day' => $day['day'],
                    'status' => $dayStatus,
                    'is_weekend' => $day['is_weekend'],
                    'is_holiday' => $day['is_holiday']
                ];

                // Update monthly summary
                $monthlySummary[$dayStatus . '_days']++;
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

    /**
     * Calculate attendance status for a specific day
     */
    private function calculateDayStatus($studentId, $date, $schedules, $attendanceData, $isNonSchoolDay): string
    {
        // Skip weekends and holidays (no classes scheduled)
        if ($isNonSchoolDay) {
            return 'no_class';
        }

        // Get attendance records for this student on this date
        $dayAttendance = $attendanceData[$studentId][$date] ?? collect();

        // If no attendance records, assume absent (if there should be classes)
        if ($dayAttendance->isEmpty()) {
            return $schedules->isNotEmpty() ? 'absent' : 'no_class';
        }

        // Count attendance by status
        $totalSubjects = $schedules->count();
        $attendedSubjects = $dayAttendance->whereIn('status', ['present', 'late'])->count();
        $recordedSubjects = $dayAttendance->count();

        // Determine day status based on attendance
        if ($attendedSubjects == $totalSubjects && $recordedSubjects == $totalSubjects) {
            return 'present'; // Present for all subjects
        } elseif ($attendedSubjects > 0) {
            return 'half_day'; // Present for some subjects, absent for others
        } else {
            return 'absent'; // Absent for all subjects or marked absent for all recorded subjects
        }
    }

    /**
     * Check if a date is a holiday
     */
    private function isHoliday($date): bool
    {
        // Implement your holiday checking logic here
        // You might have a holidays table or use a holiday API
        return false;
    }

    /**
     * Calculate attendance rate
     */
    private function calculateAttendanceRate($summary): float
    {
        $totalSchoolDays = $summary['present_days'] + $summary['half_days'] + $summary['absent_days'];

        if ($totalSchoolDays == 0) {
            return 0;
        }

        // Calculate rate: present days + (half days * 0.5)
        $attendanceScore = $summary['present_days'] + ($summary['half_days'] * 0.5);

        return round(($attendanceScore / $totalSchoolDays) * 100, 2);
    }

    /**
     * Calculate overall class statistics
     */
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

    /**
     * Return error response
     */
    private function errorResponse($message, $statusCode = 500, $error = null)
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
