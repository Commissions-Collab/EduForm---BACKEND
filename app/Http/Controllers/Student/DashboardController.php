<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\BookInventory;
use App\Models\Grade;
use App\Models\Quarter;
use App\Models\StudentBorrowBook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();
            $student = $user->student;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            $currentYear = $this->getCurrentAcademicYear();

            if (!$currentYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active academic year found'
                ], 404);
            }

            $today = Carbon::today();

            // Get current and previous quarters
            $currentQuarter = Quarter::where('academic_year_id', $currentYear->id)
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $previousQuarter = null;
            if ($currentQuarter) {
                $previousQuarter = Quarter::where('academic_year_id', $currentYear->id)
                    ->where('id', '<', $currentQuarter->id)
                    ->orderByDesc('id')
                    ->first();
            }

            // Get grade averages
            $currentAverage = $this->getQuarterAverage($student->id, $currentQuarter?->id);
            $previousAverage = $this->getQuarterAverage($student->id, $previousQuarter?->id);

            // Calculate grade change percentage
            $gradeChangePercentage = 0;
            if (
                is_numeric($previousAverage) &&
                $previousAverage > 0 &&
                is_numeric($currentAverage)
            ) {
                $gradeChangePercentage = round((($currentAverage - $previousAverage) / $previousAverage) * 100, 2);
            }

            // Get grades by subject with averages
            $grades = Grade::with('subject')
                ->select('subject_id', DB::raw('AVG(grade) as average_grade'))
                ->where('student_id', $student->id)
                ->where('academic_year_id', $currentYear->id)
                ->groupBy('subject_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'subject_id' => $item->subject_id,
                        'subject' => $item->subject?->name ?? 'Unknown Subject',
                        'average_grade' => round($item->average_grade ?? 0, 2),
                    ];
                });

            $totalAverage = $grades->count() > 0
                ? round(collect($grades)->pluck('average_grade')->avg(), 2)
                : 0;

            // Get attendance records for current academic year
            $attendanceRecords = Attendance::where('student_id', $student->id)
                ->where('academic_year_id', $currentYear->id)
                ->get();

            $totalDays = $attendanceRecords->count();
            $present = $attendanceRecords->where('status', 'present')->count();
            $presentPercent = $totalDays > 0 ? round(($present / $totalDays) * 100) : 0;

            // Get recent absences
            $recentAbsents = $attendanceRecords
                ->where('status', 'absent')
                ->sortByDesc('attendance_date')
                ->take(5)
                ->map(function ($record) {
                    return [
                        'attendance_date' => $record->attendance_date,
                        'status' => $record->status,
                        'remarks' => $record->remarks,
                    ];
                })
                ->values()
                ->toArray();

            $attendanceSummary = [
                'present_percent' => $presentPercent,
                'recent_absents' => $recentAbsents
            ];

            // Get book borrowing information
            $bookBorrow = StudentBorrowBook::with('bookInventory')
                ->where('student_id', $student->id)
                ->get();

            $borrowCount = $bookBorrow->count();

            // Calculate books due this week
            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();

            $bookDueThisWeek = $bookBorrow->filter(function ($borrow) use ($startOfWeek, $endOfWeek) {
                if (!$borrow->due_date && !$borrow->expected_return_date) {
                    return false;
                }

                $dueDate = Carbon::parse(
                    $borrow->due_date ?? $borrow->expected_return_date
                );

                return $dueDate->between($startOfWeek, $endOfWeek);
            })->count();

            // Get important notifications
            $notifications = $this->getImportantNotifications($student->id, $currentYear->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'grades' => [
                        'total_average' => $totalAverage,
                        'subjects' => $grades->values()->toArray()
                    ],
                    'grade_change_percent' => $gradeChangePercentage,
                    'attendance_rate' => $attendanceSummary,
                    'borrow_book' => $borrowCount,
                    'book_due_this_week' => $bookDueThisWeek,
                    'notifications' => $notifications
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard index error:', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current academic year
     * Tries boolean first, then integer, then gets most recent
     */
    private function getCurrentAcademicYear()
    {
        try {
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

            return $academicYear;
        } catch (\Exception $e) {
            Log::error('Get current academic year error:', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get quarter average for a student
     */
    private function getQuarterAverage($studentId, $quarterId)
    {
        try {
            if (!$quarterId) {
                return null;
            }

            return Grade::where('student_id', $studentId)
                ->where('quarter_id', $quarterId)
                ->avg('grade');
        } catch (\Exception $e) {
            Log::error('Get quarter average error:', [
                'student_id' => $studentId,
                'quarter_id' => $quarterId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get important notifications for student
     * Includes grade qualifications and book due dates
     */
    private function getImportantNotifications($studentId, $academicYearId)
    {
        try {
            $notifications = [];

            // Get grades and check for honors eligibility
            $grades = Grade::with('subject')
                ->select('subject_id', DB::raw('AVG(grade) as average_grade'))
                ->where('student_id', $studentId)
                ->where('academic_year_id', $academicYearId)
                ->groupBy('subject_id')
                ->get();

            // Get class averages for comparison
            $classAverages = Grade::select('subject_id', DB::raw('AVG(grade) as class_avg'))
                ->where('academic_year_id', $academicYearId)
                ->groupBy('subject_id')
                ->pluck('class_avg', 'subject_id');

            $totalAverage = $grades->count() > 0
                ? round($grades->avg('average_grade'), 2)
                : 0;

            // Check each subject for below class average
            foreach ($grades as $grade) {
                $subject = $grade->subject?->name ?? 'Unknown Subject';
                $classAvg = $classAverages[$grade->subject_id] ?? null;

                if ($classAvg && $grade->average_grade < $classAvg) {
                    $notifications[] = "Your grade in {$subject} is below the class average.";
                }
            }

            // Check honors eligibility
            if ($totalAverage >= 90 && $totalAverage < 95) {
                $notifications[] = "You are eligible for With Honors.";
            } elseif ($totalAverage >= 95 && $totalAverage < 98) {
                $notifications[] = "You are eligible for With High Honors.";
            } elseif ($totalAverage >= 98 && $totalAverage <= 100) {
                $notifications[] = "You are eligible for With Highest Honors.";
            }

            // Check book return notifications
            $borrowedBooks = StudentBorrowBook::with('bookInventory')
                ->where('student_id', $studentId)
                ->whereIn('status', ['issued', 'overdue'])
                ->get();

            foreach ($borrowedBooks as $borrow) {
                // Use due_date if available, otherwise expected_return_date
                $dueDate = $borrow->due_date ?? $borrow->expected_return_date;

                if (!$dueDate) {
                    continue;
                }

                $due = Carbon::parse($dueDate);
                $daysLeft = now()->diffInDays($due, false);
                $title = $borrow->bookInventory?->title ?? 'Unknown Book';

                if ($daysLeft < 0) {
                    $daysOverdue = round(abs($daysLeft), 2);
                    $notifications[] = "The book '{$title}' is overdue by {$daysOverdue} day(s).";
                } elseif ($daysLeft <= 3) {
                    $notifications[] = "The book '{$title}' is due in {$daysLeft} day(s).";
                }
            }

            return $notifications;
        } catch (\Exception $e) {
            Log::error('Get important notifications error:', [
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}
