<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Quarter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentAttendanceController extends Controller
{
    public function attendanceRecords(Request $request)
    {
        try {
            $student = Auth::user()->student;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            // Parse month from request
            $month = $request->input('month');
            if (!$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'Month parameter is required'
                ], 400);
            }

            $date = Carbon::createFromFormat('Y-m', $month);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $academicYear = $this->getCurrentAcademicYear();

            // Fetch monthly attendance records
            $monthlyRecords = Attendance::where('student_id', $student->id)
                ->whereBetween('attendance_date', [$startOfMonth, $endOfMonth])
                ->where('academic_year_id', $academicYear->id)
                ->with('schedule')
                ->get();

            $totalDays = $monthlyRecords->count();
            $presentDays = $monthlyRecords->where('status', 'present')->count();
            $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100) : 0;

            // Late Arrivals Analysis
            $lateRecords = $monthlyRecords->where('status', 'late');
            $lateCount = $lateRecords->count();

            $lateWeekdayPattern = null;
            if ($lateCount > 0) {
                $weekdayCount = $lateRecords->countBy(fn($r) => Carbon::parse($r->attendance_date)->format('l'));
                $lateWeekdayPattern = $weekdayCount->sortDesc()->keys()->first();
            }

            // Absences
            $absentCount = $monthlyRecords->where('status', 'absent')->count();

            // Format daily status
            $dailyStatus = $monthlyRecords->map(function ($rec) {
                return [
                    'date' => Carbon::parse($rec->attendance_date)->format('Y-m-d'),
                    'weekday' => Carbon::parse($rec->attendance_date)->format('l'),
                    'status' => $rec->status,
                    'remarks' => $rec->remarks,
                    'time_in' => $rec->time_in,
                    'expected_time' => $rec->schedule ? $rec->schedule->start_time : null,
                ];
            })->sortBy('date')->values();

            // Quarterly Summary
            $quarters = Quarter::where('academic_year_id', $academicYear->id)
                ->orderBy('start_date')
                ->get();

            $quarterSummary = $quarters->map(function ($quarter) use ($student) {
                $records = Attendance::where('student_id', $student->id)
                    ->whereBetween('attendance_date', [$quarter->start_date, $quarter->end_date])
                    ->get();

                $total = $records->count();
                $present = $records->where('status', 'present')->count();

                return [
                    'quarter_id' => $quarter->id,
                    'quarter' => $quarter->name,
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100) : 0,
                    'total_days' => $total,
                    'present_days' => $present,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'attendance_rate' => $attendanceRate,
                    'late_arrivals' => [
                        'count' => $lateCount,
                        'pattern' => $lateWeekdayPattern,
                    ],
                    'absences' => [
                        'count' => $absentCount,
                    ],
                    'daily_status' => $dailyStatus,
                    'quarterly_summary' => $quarterSummary->values(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function attendanceMonthFilter()
    {
        try {
            $student = Auth::user()->student;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            $academicYear = $this->getCurrentAcademicYear();

            if (!$academicYear || !$academicYear->start_date || !$academicYear->end_date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid academic year dates'
                ], 422);
            }

            $startDate = Carbon::parse($academicYear->start_date)->startOfMonth();
            $endDate = Carbon::parse($academicYear->end_date)->endOfMonth();

            $months = [];
            $current = $startDate->copy();

            while ($current->lte($endDate)) {
                $months[] = [
                    'label' => $current->format('F Y'),
                    'value' => $current->format('Y-m')
                ];
                $current->addMonth();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'months' => $months
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch month filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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

            if (!$academicYear) {
                throw new \Exception('No academic year found in database');
            }

            return $academicYear;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
