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
            // Fallback: try to derive section from teacher's advisedSections pivot for current year
            $section = $teacher->advisedSections()
                ->wherePivot('academic_year_id', $academicYear->id)
                ->first();

            if ($section) {
                // Create a lightweight advisor object so the rest of the logic can run
                $advisor = new \stdClass();
                $advisor->section_id = $section->id;
                $advisor->section = $section;
            } else {
                // Fallback: try to use teacher's schedules to infer a section
                $schedule = $teacher->schedules()
                    ->where('academic_year_id', $academicYear->id)
                    ->first();

                if ($schedule) {
                    $sec = \App\Models\Section::find($schedule->section_id);
                    if ($sec) {
                        $advisor = new \stdClass();
                        $advisor->section_id = $sec->id;
                        $advisor->section = $sec;
                    }
                }
            }

            // If still no advisor/section found, return an empty successful payload
            if (!$advisor) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'today_attendance' => [
                            'present' => 0,
                            'absent' => 0,
                            'late' => 0,
                            'present_percentage' => 0,
                            'absent_percentage' => 0,
                            'late_percentage' => 0
                        ],
                        'academic_status' => [
                            'report_cards' => 0,
                            'honors_eligible' => 0,
                            'grades_submitted_percentage' => 0
                        ],
                        'resources_calendar' => [
                            'textbook_overdues' => 0,
                            'pending_returns' => 0,
                            'upcoming_events' => []
                        ],
                        'weekly_summary' => [
                            'attendance_trends' => [
                                'average_daily' => 0,
                                'best_day' => 'No data',
                            ],
                            'academic_updates' => [
                                'grades_submitted' => 0,
                            ],
                            'system_status' => [
                                'active_users' => 0
                            ]
                        ],
                        'section_info' => [
                            'section_name' => 'N/A',
                            'total_students' => 0,
                            'academic_year' => $academicYear->name ?? 'N/A',
                            'section_id' => null
                        ]
                    ]
                ]);
            }
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
    // Prevent percentage from exceeding 100% (in cases where grades are per-subject)
    $calculatedPercentage = $totalStudents > 0 ? round(($totalGradesSubmitted / $totalStudents) * 100, 0) : 0;
    $gradesSubmittedPercentage = $calculatedPercentage > 100 ? 100 : $calculatedPercentage;

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

        // Get pending returns: issued books that are due within the next 7 days (not yet overdue)
        $today = now()->startOfDay();
        $nextWeek = now()->endOfDay()->addDays(7);

        $pendingReturns = StudentBorrowBook::whereIn('student_id', function ($query) use ($advisor, $academicYear) {
            $query->select('student_id')
                ->from('enrollments')
                ->where('section_id', $advisor->section_id)
                ->where('academic_year_id', $academicYear->id)
                ->where('enrollment_status', 'enrolled');
        })
            ->where('status', 'issued')
            ->whereBetween('due_date', [$today, $nextWeek])
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
