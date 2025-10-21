<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SectionAdvisor;
use App\Models\StudentBorrowBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function dashboardData(Request $request)
    {
        $teacher = Auth::user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 401);
        }

        $academicYear = $this->getCurrentAcademicYear();
        $currentDate = $request->get('attendance_date', now()->format('Y-m-d'));
        $quarterId = $request->get('quarter_id');

        // Get section advisor info
        $advisor = SectionAdvisor::with('section')
            ->where('teacher_id', $teacher->id)
            ->where('academic_year_id', $academicYear->id)
            ->first();

        if (!$advisor) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned as a section advisor for this academic year'
            ], 404);
        }

        // Get all students in the section through enrollments
        $totalStudents = Enrollment::where('section_id', $advisor->section_id)
            ->where('academic_year_id', $academicYear->id)
            ->where('enrollment_status', 'enrolled')
            ->count();

        // Get today's attendance data for the section
        $todayAttendance = Attendance::whereHas('schedule', function ($query) use ($advisor, $academicYear, $quarterId) {
            $query->where('section_id', $advisor->section_id)
                ->where('academic_year_id', $academicYear->id);
            if ($quarterId) {
                $query->where('quarter_id', $quarterId);
            }
        })
            ->where('attendance_date', $currentDate)
            ->get();

        // Calculate attendance statistics
        $present = $todayAttendance->where('status', 'present')->count();
        $absent = $todayAttendance->where('status', 'absent')->count();
        $late = $todayAttendance->where('status', 'late')->count();

        $totalAttendance = $todayAttendance->count();
        $presentPercentage = $totalAttendance > 0 ? round($present / $totalAttendance * 100, 1) : 0;
        $absentPercentage = $totalAttendance > 0 ? round($absent / $totalAttendance * 100, 1) : 0;
        $latePercentage = $totalAttendance > 0 ? round($late / $totalAttendance * 100, 1) : 0;

        // Get grades data for academic status
        $grades = Grade::whereIn('student_id', function ($query) use ($advisor, $academicYear) {
            $query->select('student_id')
                ->from('enrollments')
                ->where('section_id', $advisor->section_id)
                ->where('academic_year_id', $academicYear->id)
                ->where('enrollment_status', 'enrolled');
        })
            ->where('academic_year_id', $academicYear->id);

        if ($quarterId) {
            $grades = $grades->where('quarter_id', $quarterId);
        }

        $gradesCollection = $grades->get();
        $totalGradesSubmitted = $gradesCollection->count();
        $gradesSubmittedPercentage = $totalStudents > 0 ? round(($totalGradesSubmitted / $totalStudents) * 100, 0) : 0;

        // Honor students (assuming 90+ average is honors eligible)
        $honorEligible = $gradesCollection->where('grade', '>=', 90)->count();

        // Get overdue books for the section
        $overdueBooks = StudentBorrowBook::whereIn('student_id', function ($query) use ($advisor, $academicYear) {
            $query->select('student_id')
                ->from('enrollments')
                ->where('section_id', $advisor->section_id)
                ->where('academic_year_id', $academicYear->id)
                ->where('enrollment_status', 'enrolled');
        })
            ->where('status', 'overdue')
            ->count();

        // Get pending returns
        $pendingReturns = StudentBorrowBook::whereIn('student_id', function ($query) use ($advisor, $academicYear) {
            $query->select('student_id')
                ->from('enrollments')
                ->where('section_id', $advisor->section_id)
                ->where('academic_year_id', $academicYear->id)
                ->where('enrollment_status', 'enrolled');
        })
            ->where('status', 'issued')
            ->where('due_date', '<', now())
            ->count();

        // Get upcoming events from academic calendar
        $upcomingEvents = AcademicCalendar::where('academic_year_id', $academicYear->id)
            ->where('date', '>=', now()->format('Y-m-d'))
            ->where('type', '!=', 'regular')
            ->orderBy('date')
            ->limit(3)
            ->get();

        // Weekly attendance trends
        $weekStart = now()->startOfWeek()->format('Y-m-d');
        $weekEnd = now()->endOfWeek()->format('Y-m-d');

        $weeklyAttendance = Attendance::whereHas('schedule', function ($query) use ($advisor, $academicYear) {
            $query->where('section_id', $advisor->section_id)
                ->where('academic_year_id', $academicYear->id);
        })
            ->whereBetween('attendance_date', [$weekStart, $weekEnd])
            ->get();

        $weeklyStats = [];
        $totalWeeklyAttendance = $weeklyAttendance->count();
        $averageDaily = $totalWeeklyAttendance > 0 ?
            round($weeklyAttendance->where('status', 'present')->count() / $totalWeeklyAttendance * 100, 1) : 0;

        // Find best attendance day this week
        $dailyStats = $weeklyAttendance->groupBy('attendance_date');
        $bestDay = '';
        $bestPercentage = 0;

        foreach ($dailyStats as $date => $dayAttendance) {
            $dayPresent = $dayAttendance->where('status', 'present')->count();
            $dayTotal = $dayAttendance->count();
            $dayPercentage = $dayTotal > 0 ? ($dayPresent / $dayTotal) * 100 : 0;

            if ($dayPercentage > $bestPercentage) {
                $bestPercentage = $dayPercentage;
                $bestDay = now()->parse($date)->format('l') . ' (' . round($dayPercentage, 1) . '%)';
            }
        }

        $activeUser = Enrollment::where('enrollment_status', 'enrolled')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'today_attendance' => [
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'present_percentage' => $presentPercentage,
                    'absent_percentage' => $absentPercentage,
                    'late_percentage' => $latePercentage
                ],
                'academic_status' => [
                    'report_cards' => $totalGradesSubmitted,
                    'honors_eligible' => $honorEligible,
                    'grades_submitted_percentage' => $gradesSubmittedPercentage
                ],
                'resources_calendar' => [
                    'textbook_overdues' => $overdueBooks,
                    'pending_returns' => $pendingReturns,
                    'upcoming_events' => $upcomingEvents->map(function ($event) {
                        return [
                            'title' => $event->title,
                            'date' => $event->date,
                            'type' => $event->type
                        ];
                    })
                ],
                'weekly_summary' => [
                    'attendance_trends' => [
                        'average_daily' => $averageDaily,
                        'best_day' => $bestDay ?: 'No data',
                    ],
                    'academic_updates' => [
                        'grades_submitted' => $gradesSubmittedPercentage,
                    ],
                    'system_status' => [
                        'active_users' => $activeUser // This would come from active sessions
                    ]
                ],
                'section_info' => [
                    'section_name' => $advisor->section->name ?? 'N/A',
                    'total_students' => $totalStudents,
                    'academic_year' => $academicYear->name ?? 'N/A',
                    'section_id' => $advisor->section_id
                ]
            ]
        ]);
    }

    private function getCurrentAcademicYear()
    {
        $year = AcademicYear::where('is_current', 1)->first();
        if (!$year) {
            abort(404, 'Active academic year not found.');
        }
        return $year;
    }
}
