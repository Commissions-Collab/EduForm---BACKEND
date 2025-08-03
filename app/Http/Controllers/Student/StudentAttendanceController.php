<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Quarter;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentAttendanceController extends Controller
{
    public function attendanceRecords(Request $request)
    {
        $student = Auth::user();
        $date = Carbon::createFromFormat('Y-m', $request->input('month'));
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $academicYear = $this->getCurrentAcademicYear();

        $monthlyRecords = Attendance::where('student_id', $student->id)
            ->whereBetween('attendance_date', [$startOfMonth, $endOfMonth])
            ->where('academic_year_id', $academicYear->id)
            ->with('schedule') // Load schedule relationship to get expected start time
            ->get();

        $totalDays = $monthlyRecords->count();
        $presentDays = $monthlyRecords->where('status', 'present')->count();
        $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100) : 0;

        // Late Arrivals - Fixed Pattern and Average Calculation
        $lateRecords = $monthlyRecords->where('status', 'late');
        $lateCount = $lateRecords->count();

        // Get the most frequent weekday for late arrivals (fixed)
        $lateWeekdayPattern = null;
        if ($lateCount > 0) {
            $weekdayCount = $lateRecords->countBy(fn($r) => Carbon::parse($r->attendance_date)->format('l'));
            $lateWeekdayPattern = $weekdayCount->sortDesc()->keys()->first();
        }

        // Calculate average delay in minutes
        // $averageDelay = 0;
        // if ($lateCount > 0) {
        //     $totalDelayMinutes = 0;
        //     $validLateRecords = 0;

        //     foreach ($lateRecords as $record) {
        //         if ($record->time_in && $record->schedule && $record->schedule->start_time) {
        //             try {
        //                 // Create Carbon instances for time comparison
        //                 $expectedTime = Carbon::createFromFormat('H:i:s', $record->schedule->start_time);
        //                 $actualTime = Carbon::createFromFormat('H:i:s', $record->time_in);

        //                 if ($actualTime->gt($expectedTime)) {
        //                     $delayMinutes = $actualTime->diffInMinutes($expectedTime);
        //                     $totalDelayMinutes += $delayMinutes;
        //                     $validLateRecords++;
        //                 }
        //             } catch (\Exception $e) {
        //                 // Skip this record if time parsing fails
        //                 continue;
        //             }
        //         }
        //     }

        //     $averageDelay = $validLateRecords > 0 ? round($totalDelayMinutes / $validLateRecords) : 0;
        // }

        // Absences
        $absentCount = $monthlyRecords->where('status', 'absent')->count();

        $dailyStatus = $monthlyRecords->map(function ($rec) {
            // $minutesLate = 0;

            // // Calculate minutes late if time_in and schedule are available
            // if ($rec->status === 'late' && $rec->time_in && $rec->schedule && $rec->schedule->start_time) {
            //     try {
            //         // Create Carbon instances for time comparison
            //         $expectedTime = Carbon::createFromFormat('H:i:s', $rec->schedule->start_time);
            //         $actualTime = Carbon::createFromFormat('H:i:s', $rec->time_in);

            //         if ($actualTime->gt($expectedTime)) {
            //             $minutesLate = $actualTime->diffInMinutes($expectedTime);
            //         }
            //     } catch (\Exception $e) {
            //         // If time parsing fails, set minutes late to 0
            //         $minutesLate = 0;
            //     }
            // }

            return [
                'date' => Carbon::parse($rec->attendance_date)->format('Y-m-d'),
                'weekday' => Carbon::parse($rec->attendance_date)->format('l'),
                'status' => $rec->status,
                'remarks' => $rec->remarks,
                'time_in' => $rec->time_in,
                'expected_time' => $rec->schedule ? $rec->schedule->start_time : null,
            ];
        })->sortBy('date')->values();

        $quarters = Quarter::where('academic_year_id', $academicYear->id)->get();
        $quarterSummary = $quarters->map(function ($quarter) use ($student) {
            $records = Attendance::where('student_id', $student->id)
                ->whereBetween('attendance_date', [$quarter->start_date, $quarter->end_date])
                ->get();

            $total = $records->count();
            $present = $records->where('status', 'present')->count();

            return [
                'quarter' => $quarter->name,
                'attendance_rate' => $total > 0 ? round(($present / $total) * 100) : 0,
            ];
        });

        return response()->json([
            'attendance_rate' => $attendanceRate,
            'late_arrivals' => [
                'count' => $lateCount,
                'pattern' => $lateWeekdayPattern,
                // 'average_delay_minutes' => $averageDelay,
            ],
            'absences' => [
                'count' => $absentCount,
            ],
            'daily_status' => $dailyStatus,
            'quarterly_summary' => $quarterSummary,
        ]);
    }

    public function attendanceMonthFilter()
    {
        $academicYear = $this->getCurrentAcademicYear();

        if (!$academicYear || !$academicYear->start_date || !$academicYear->end_date) {
            return response()->json(['message' => 'Invalid academic year dates'], 422);
        }

        // Make sure dates are Carbon instances
        $startDate = Carbon::parse($academicYear->start_date)->startOfMonth();
        $endDate = Carbon::parse($academicYear->end_date)->endOfMonth();

        $months = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $months[] = [
                'label' => $current->format('F Y'), // June 2025
                'value' => $current->format('Y-m')  // 2025-06
            ];
            $current->addMonth();
        }

        return response()->json([
            'months' => $months
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
