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
        $student = Auth::user();
        $currentYear = $this->getCurrentAcademicYear();

        $today = Carbon::today();
        $currentQuarter = Quarter::where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first();

        $previousQuarter = null;
        if ($currentQuarter) {
            $previousQuarter = Quarter::where('academic_year_id', $currentYear->id)
                ->where('id', '<', $currentQuarter->id)
                ->orderByDesc('id')
                ->first();
        }

        $currentAverage = $this->getQuarterAverage($student->id, $currentQuarter?->id);
        $previousAverage = $this->getQuarterAverage($student->id, $previousQuarter?->id);

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
            ->groupBy('subject_id')
            ->get()
            ->map(function ($item) {
                return [
                    'subject' => $item->subject->name ?? 'Unknown Subject',
                    'average_grade' => round($item->average_grade, 2),
                ];
            });

        $totalAverage = $grades->count() > 0
            ? round(collect($grades)->pluck('average_grade')->avg(), 2)
            : 0;

        $attendanceRecords = Attendance::where('student_id', $student->id)
            ->where('academic_year_id', $currentYear->id)
            ->get();

        $totalDays = $attendanceRecords->count();
        $present = $attendanceRecords->where('status', 'present')->count();

        $attendanceSummary = [
            'present_percent' => $totalDays ? round(($present / $totalDays) * 100) : 0,
            'recent_absents' => $attendanceRecords->where('status', 'absent')
                ->sortByDesc('attendance_date')
                ->take(5)
                ->values()
                ->toArray()
        ];

        $bookBorrow = StudentBorrowBook::with('bookInventory')
            ->where('student_id', $student->id)
            ->get();

        $borrowCount = $bookBorrow->count();

        $bookDueThisWeek = $bookBorrow->filter(function ($borrow) {
            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();
            $expectedReturn = Carbon::parse($borrow->expected_return_date);
            return $expectedReturn->between($startOfWeek, $endOfWeek);
        });

        $notifications = $this->getImportantNotifications($student->id, $currentYear->id);

        return response()->json([
            'grades' => [
                'total_average' => $totalAverage,
                'subjects' => $grades->toArray()
            ],
            'grade_change_percent' => $gradeChangePercentage,
            'attendance_rate' => $attendanceSummary,
            'borrow_book' => $borrowCount,
            'book_due_this_week' => $bookDueThisWeek->count(),
            'notifications' => $notifications
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

    private function getQuarterAverage($studentId, $quarterId)
    {
        if (!$quarterId) return null;

        return Grade::where('student_id', $studentId)
            ->where('quarter_id', $quarterId)
            ->avg('grade');
    }


    private function getImportantNotifications($studentId, $academicYearId)
    {
        $notifications = [];

        // Grades and honor qualification
        $grades = Grade::with('subject')
            ->select('subject_id', DB::raw('AVG(grade) as average_grade'))
            ->where('student_id', $studentId)
            ->groupBy('subject_id')
            ->get();

        $classAverages = Grade::select('subject_id', DB::raw('AVG(grade) as class_avg'))
            ->where('academic_year_id', $academicYearId)
            ->groupBy('subject_id')
            ->pluck('class_avg', 'subject_id');

        $totalAverage = $grades->count() > 0 ? round($grades->avg('average_grade'), 2) : 0;

        foreach ($grades as $grade) {
            $subject = $grade->subject->name ?? 'Unknown Subject';
            $classAvg = $classAverages[$grade->subject_id] ?? null;

            if ($classAvg && $grade->average_grade < $classAvg) {
                $notifications[] = "Your grade in {$subject} is below the class average.";
            }
        }

        if ($totalAverage >= 90 && $totalAverage < 95) {
            $notifications[] = "You are eligible for With Honors.";
        } elseif ($totalAverage >= 95 && $totalAverage < 98) {
            $notifications[] = "You are eligible for With High Honors.";
        } elseif ($totalAverage >= 98 && $totalAverage <= 100) {
            $notifications[] = "You are eligible for With Highest Honors.";
        }

        // Book return notifications
        $borrowedBooks = StudentBorrowBook::with('bookInventory')
            ->where('student_id', $studentId)
            ->get();

        foreach ($borrowedBooks as $borrow) {
            Log::debug($borrow->bookInventory);
            $due = Carbon::parse($borrow->expected_return_date);
            $daysLeft = now()->diffInDays($due, false);
            $title = $borrow->bookInventory->title ?? 'Unknown Book';

            if ($daysLeft < 0) {
                $notifications[] = "The book '{$title}' is overdue by " . round(abs($daysLeft), 2) . " day(s).";
            } elseif ($daysLeft <= 3) {
                $notifications[] = "The book '{$title}' is due in {$daysLeft} day(s).";
            }
        }

        return $notifications;
    }
}
