<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Enrollment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

    /**
     * Export SF4 Excel - Monthly Learner's Movement and Attendance
     * This aggregates data across all sections for a given academic year and month
     */
    public function exportSF4Excel(Request $request)
    {
        try {
            $request->validate([
                'academic_year_id' => 'required|exists:academic_years,id',
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2000',
            ]);

            $academicYearId = $request->get('academic_year_id');
            $month = $request->get('month');
            $year = $request->get('year');

            $academicYear = AcademicYear::findOrFail($academicYearId);

            // Get date range for the month
            $startDate = Carbon::create($year, $month, 1);
            $endDate = $startDate->copy()->endOfMonth();
            $previousMonthEnd = $startDate->copy()->subMonth()->endOfMonth();

            // Get all sections for this academic year, grouped by year level
            $sections = Section::where('academic_year_id', $academicYearId)
                ->with(['yearLevel', 'sectionAdvisors.teacher'])
                ->get()
                ->groupBy('yearLevel.name');

            // Process data for each section
            $sectionData = [];
            $gradeLevelTotals = [];

            foreach ($sections as $yearLevelName => $sectionsInLevel) {
                $gradeLevelTotals[$yearLevelName] = [
                    'registered' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'attendance_daily_avg' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'attendance_percentage' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'dropped_prev' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'dropped_month' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'dropped_cumulative' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'transferred_out_prev' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'transferred_out_month' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'transferred_out_cumulative' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'transferred_in_prev' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'transferred_in_month' => ['male' => 0, 'female' => 0, 'total' => 0],
                    'transferred_in_cumulative' => ['male' => 0, 'female' => 0, 'total' => 0],
                ];

                foreach ($sectionsInLevel as $section) {
                    $sectionStats = $this->calculateSectionSF4Stats($section, $academicYearId, $startDate, $endDate, $previousMonthEnd);
                    $sectionData[] = [
                        'year_level' => $yearLevelName,
                        'section' => $section,
                        'stats' => $sectionStats
                    ];

                    // Add to grade level totals
                    foreach ($sectionStats as $key => $values) {
                        if (isset($gradeLevelTotals[$yearLevelName][$key])) {
                            $gradeLevelTotals[$yearLevelName][$key]['male'] += $values['male'] ?? 0;
                            $gradeLevelTotals[$yearLevelName][$key]['female'] += $values['female'] ?? 0;
                            $gradeLevelTotals[$yearLevelName][$key]['total'] += $values['total'] ?? 0;
                        }
                    }
                }
            }

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SF4 Monthly Summary');

            // Set column widths
            $colWidths = [
                'A' => 15, // GRADE/YEAR LEVEL
                'B' => 15, // SECTION
                'C' => 20, // NAME OF ADVISER
                'D' => 12, // REGISTERED LEARNERS - M
                'E' => 12, // REGISTERED LEARNERS - F
                'F' => 12, // REGISTERED LEARNERS - T
                'G' => 12, // ATTENDANCE Daily Avg - M
                'H' => 12, // ATTENDANCE Daily Avg - F
                'I' => 12, // ATTENDANCE Daily Avg - T
                'J' => 12, // ATTENDANCE % - M
                'K' => 12, // ATTENDANCE % - F
                'L' => 12, // ATTENDANCE % - T
                'M' => 12, // DROPPED OUT (A) - M
                'N' => 12, // DROPPED OUT (A) - F
                'O' => 12, // DROPPED OUT (A) - T
                'P' => 12, // DROPPED OUT (B) - M
                'Q' => 12, // DROPPED OUT (B) - F
                'R' => 12, // DROPPED OUT (B) - T
                'S' => 12, // DROPPED OUT (A+B) - M
                'T' => 12, // DROPPED OUT (A+B) - F
                'U' => 12, // DROPPED OUT (A+B) - T
                'V' => 12, // TRANSFERRED OUT (A) - M
                'W' => 12, // TRANSFERRED OUT (A) - F
                'X' => 12, // TRANSFERRED OUT (A) - T
                'Y' => 12, // TRANSFERRED OUT (B) - M
                'Z' => 12, // TRANSFERRED OUT (B) - F
                'AA' => 12, // TRANSFERRED OUT (B) - T
                'AB' => 12, // TRANSFERRED OUT (A+B) - M
                'AC' => 12, // TRANSFERRED OUT (A+B) - F
                'AD' => 12, // TRANSFERRED OUT (A+B) - T
                'AE' => 12, // TRANSFERRED IN (A) - M
                'AF' => 12, // TRANSFERRED IN (A) - F
                'AG' => 12, // TRANSFERRED IN (A) - T
                'AH' => 12, // TRANSFERRED IN (B) - M
                'AI' => 12, // TRANSFERRED IN (B) - F
                'AJ' => 12, // TRANSFERRED IN (B) - T
                'AK' => 12, // TRANSFERRED IN (A+B) - M
                'AL' => 12, // TRANSFERRED IN (A+B) - F
                'AM' => 12, // TRANSFERRED IN (A+B) - T
            ];

            foreach ($colWidths as $col => $width) {
                $sheet->getColumnDimension($col)->setWidth($width);
            }

            // Header Section
            $row = 1;
            $sheet->setCellValue('A' . $row, 'School Form 4 (SF4) Monthly Learner\'s Movement and Attendance');
            $sheet->mergeCells('A' . $row . ':AM' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $sheet->setCellValue('A' . $row, '(This replaces Form 3 & STS Form 4-Absenteeism and Dropout Profile)');
            $sheet->mergeCells('A' . $row . ':AM' . $row);
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
            $sheet->setCellValue('B' . $row, $startDate->format('F Y'));

            $row += 2;

            // Table Header - Complex multi-row header
            $headerRow = $row;
            
            // First header row
            $sheet->setCellValue('A' . $row, 'GRADE/ YEAR LEVEL');
            $sheet->mergeCells('A' . $row . ':A' . ($row + 1));
            $sheet->setCellValue('B' . $row, 'SECTION');
            $sheet->mergeCells('B' . $row . ':B' . ($row + 1));
            $sheet->setCellValue('C' . $row, 'NAME OF ADVISER');
            $sheet->mergeCells('C' . $row . ':C' . ($row + 1));
            $sheet->setCellValue('D' . $row, 'REGISTERED LEARNERS');
            $sheet->mergeCells('D' . $row . ':F' . $row + 1);
            $sheet->setCellValue('G' . $row, 'ATTENDANCE');
            $sheet->mergeCells('G' . $row . ':L' . $row);
            $sheet->setCellValue('M' . $row, 'DROPPED OUT');
            $sheet->mergeCells('M' . $row . ':U' . $row);
            $sheet->setCellValue('V' . $row, 'TRANSFERRED OUT');
            $sheet->mergeCells('V' . $row . ':AD' . $row);
            $sheet->setCellValue('AE' . $row, 'TRANSFERRED IN');
            $sheet->mergeCells('AE' . $row . ':AM' . $row);

            // Second header row
            $row++;
            
            $sheet->setCellValue('G' . $row, 'Daily Average');
            $sheet->mergeCells('G' . $row . ':I' . $row);
            $sheet->setCellValue('J' . $row, 'Percentage for the Month');
            $sheet->mergeCells('J' . $row . ':L' . $row);
            
            $sheet->setCellValue('M' . $row, '(A) Cumulative as of Previous Month');
            $sheet->mergeCells('M' . $row . ':O' . $row);
            $sheet->setCellValue('P' . $row, '(B) For the Month');
            $sheet->mergeCells('P' . $row . ':R' . $row);
            $sheet->setCellValue('S' . $row, '(A+B) Cumulative as of End of the Month');
            $sheet->mergeCells('S' . $row . ':U' . $row);
            
            $sheet->setCellValue('V' . $row, '(A) Cumulative as of Previous Month');
            $sheet->mergeCells('V' . $row . ':X' . $row);
            $sheet->setCellValue('Y' . $row, '(B) For the Month');
            $sheet->mergeCells('Y' . $row . ':AA' . $row);
            $sheet->setCellValue('AB' . $row, '(A+B) Cumulative as of End of the Month');
            $sheet->mergeCells('AB' . $row . ':AD' . $row);
            
            $sheet->setCellValue('AE' . $row, '(A) Cumulative as of Previous Month');
            $sheet->mergeCells('AE' . $row . ':AG' . $row);
            $sheet->setCellValue('AH' . $row, '(B) For the Month');
            $sheet->mergeCells('AH' . $row . ':AJ' . $row);
            $sheet->setCellValue('AK' . $row, '(A+B) Cumulative as of End of the Month');
            $sheet->mergeCells('AK' . $row . ':AM' . $row);

            // Third header row - M/F/T sub-columns
            $row++;
            $sheet->setCellValue('D' . $row, 'M');
            $sheet->setCellValue('E' . $row, 'F');
            $sheet->setCellValue('F' . $row, 'T');
            $sheet->setCellValue('G' . $row, 'M');
            $sheet->setCellValue('H' . $row, 'F');
            $sheet->setCellValue('I' . $row, 'T');
            $sheet->setCellValue('J' . $row, 'M');
            $sheet->setCellValue('K' . $row, 'F');
            $sheet->setCellValue('L' . $row, 'T');
            $sheet->setCellValue('M' . $row, 'M');
            $sheet->setCellValue('N' . $row, 'F');
            $sheet->setCellValue('O' . $row, 'T');
            $sheet->setCellValue('P' . $row, 'M');
            $sheet->setCellValue('Q' . $row, 'F');
            $sheet->setCellValue('R' . $row, 'T');
            $sheet->setCellValue('S' . $row, 'M');
            $sheet->setCellValue('T' . $row, 'F');
            $sheet->setCellValue('U' . $row, 'T');
            $sheet->setCellValue('V' . $row, 'M');
            $sheet->setCellValue('W' . $row, 'F');
            $sheet->setCellValue('X' . $row, 'T');
            $sheet->setCellValue('Y' . $row, 'M');
            $sheet->setCellValue('Z' . $row, 'F');
            $sheet->setCellValue('AA' . $row, 'T');
            $sheet->setCellValue('AB' . $row, 'M');
            $sheet->setCellValue('AC' . $row, 'F');
            $sheet->setCellValue('AD' . $row, 'T');
            $sheet->setCellValue('AE' . $row, 'M');
            $sheet->setCellValue('AF' . $row, 'F');
            $sheet->setCellValue('AG' . $row, 'T');
            $sheet->setCellValue('AH' . $row, 'M');
            $sheet->setCellValue('AI' . $row, 'F');
            $sheet->setCellValue('AJ' . $row, 'T');
            $sheet->setCellValue('AK' . $row, 'M');
            $sheet->setCellValue('AL' . $row, 'F');
            $sheet->setCellValue('AM' . $row, 'T');

            // Apply header styling
            $headerRange = 'A' . $headerRow . ':AM' . $row;
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

            $row++;

            // Data rows - Group by year level
            $currentYearLevel = null;
            foreach ($sectionData as $data) {
                // Add year level header row if new level
                if ($currentYearLevel !== $data['year_level']) {
                    if ($currentYearLevel !== null) {
                        $row++; // Add spacing between grade levels
                    }
                    $currentYearLevel = $data['year_level'];
                    $sheet->setCellValue('A' . $row, $data['year_level']);
                    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('A' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('E2EFDA');
                    $row++;
                }

                $section = $data['section'];
                $stats = $data['stats'];
                $adviser = $section->sectionAdvisors->first();
                $adviserName = $adviser ? ($adviser->teacher->first_name . ' ' . $adviser->teacher->last_name) : '';

                $sheet->setCellValue('A' . $row, ''); // Year level already shown above
                $sheet->setCellValue('B' . $row, $section->name);
                $sheet->setCellValue('C' . $row, $adviserName);
                
                // Registered Learners
                $sheet->setCellValue('D' . $row, $stats['registered']['male']);
                $sheet->setCellValue('E' . $row, $stats['registered']['female']);
                $sheet->setCellValue('F' . $row, $stats['registered']['total']);
                
                // Attendance - Daily Average
                $sheet->setCellValue('G' . $row, $stats['attendance_daily_avg']['male']);
                $sheet->setCellValue('H' . $row, $stats['attendance_daily_avg']['female']);
                $sheet->setCellValue('I' . $row, $stats['attendance_daily_avg']['total']);
                
                // Attendance - Percentage
                $sheet->setCellValue('J' . $row, $stats['attendance_percentage']['male']);
                $sheet->setCellValue('K' . $row, $stats['attendance_percentage']['female']);
                $sheet->setCellValue('L' . $row, $stats['attendance_percentage']['total']);
                
                // Dropped Out
                $sheet->setCellValue('M' . $row, $stats['dropped_prev']['male']);
                $sheet->setCellValue('N' . $row, $stats['dropped_prev']['female']);
                $sheet->setCellValue('O' . $row, $stats['dropped_prev']['total']);
                $sheet->setCellValue('P' . $row, $stats['dropped_month']['male']);
                $sheet->setCellValue('Q' . $row, $stats['dropped_month']['female']);
                $sheet->setCellValue('R' . $row, $stats['dropped_month']['total']);
                $sheet->setCellValue('S' . $row, $stats['dropped_cumulative']['male']);
                $sheet->setCellValue('T' . $row, $stats['dropped_cumulative']['female']);
                $sheet->setCellValue('U' . $row, $stats['dropped_cumulative']['total']);
                
                // Transferred Out
                $sheet->setCellValue('V' . $row, $stats['transferred_out_prev']['male']);
                $sheet->setCellValue('W' . $row, $stats['transferred_out_prev']['female']);
                $sheet->setCellValue('X' . $row, $stats['transferred_out_prev']['total']);
                $sheet->setCellValue('Y' . $row, $stats['transferred_out_month']['male']);
                $sheet->setCellValue('Z' . $row, $stats['transferred_out_month']['female']);
                $sheet->setCellValue('AA' . $row, $stats['transferred_out_month']['total']);
                $sheet->setCellValue('AB' . $row, $stats['transferred_out_cumulative']['male']);
                $sheet->setCellValue('AC' . $row, $stats['transferred_out_cumulative']['female']);
                $sheet->setCellValue('AD' . $row, $stats['transferred_out_cumulative']['total']);
                
                // Transferred In
                $sheet->setCellValue('AE' . $row, $stats['transferred_in_prev']['male']);
                $sheet->setCellValue('AF' . $row, $stats['transferred_in_prev']['female']);
                $sheet->setCellValue('AG' . $row, $stats['transferred_in_prev']['total']);
                $sheet->setCellValue('AH' . $row, $stats['transferred_in_month']['male']);
                $sheet->setCellValue('AI' . $row, $stats['transferred_in_month']['female']);
                $sheet->setCellValue('AJ' . $row, $stats['transferred_in_month']['total']);
                $sheet->setCellValue('AK' . $row, $stats['transferred_in_cumulative']['male']);
                $sheet->setCellValue('AL' . $row, $stats['transferred_in_cumulative']['female']);
                $sheet->setCellValue('AM' . $row, $stats['transferred_in_cumulative']['total']);

                $row++;
            }

            // Add grade level totals
            $row++;
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFF2CC');

            // Apply borders to data area
            $dataRange = 'A' . $headerRow . ':AM' . ($row - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

            // Footer - Guidelines
            $row += 3;
            $sheet->setCellValue('A' . $row, 'GUIDELINES:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
            $row++;
            $sheet->setCellValue('A' . $row, '1. This form shall be accomplished every end of the month using the summary box of SF2 submitted by the teachers/advisers to update figures for the month.');
            $row++;
            $sheet->setCellValue('A' . $row, '2. Furnish the Division Office with a copy a week after June 30, October 30 & March 31');

            // Signature
            $row += 3;
            $sheet->setCellValue('A' . $row, 'Prepared and Submitted by:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row += 2;
            $sheet->setCellValue('A' . $row, '(Signature of School Head over Printed Name)');

            // Generate Excel file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'SF4_Monthly_Summary_' . $startDate->format('F_Y') . '_' . $academicYear->name . '.xlsx';

            ob_start();
            $writer->save('php://output');
            $content = ob_get_contents();
            ob_end_clean();

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            Log::error('SF4 Excel Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export SF4 Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate SF4 statistics for a section
     */
    private function calculateSectionSF4Stats($section, $academicYearId, $startDate, $endDate, $previousMonthEnd)
    {
        // Get enrolled students as of end of month
        $enrolledStudents = Student::whereHas('enrollments', function ($query) use ($section, $academicYearId, $endDate) {
            $query->where('section_id', $section->id)
                ->where('academic_year_id', $academicYearId)
                ->where('enrollment_status', 'enrolled')
                ->where('created_at', '<=', $endDate->copy()->endOfDay());
        })->get();

        // Count by gender
        $registered = [
            'male' => $enrolledStudents->where('gender', 'male')->count(),
            'female' => $enrolledStudents->where('gender', 'female')->count(),
            'total' => $enrolledStudents->count()
        ];

        // Get attendance data for the month
        $schedules = $this->getSectionSchedules($section->id, $academicYearId);
        $attendanceData = $this->getMonthlyAttendanceData($section->id, $academicYearId, $startDate, $endDate);
        $calendarDays = $this->generateCalendarDays($startDate, $endDate);
        $studentSummaries = $this->processStudentDailyAttendance($enrolledStudents, $schedules, $attendanceData, $calendarDays);

        // Calculate daily average attendance
        $totalDays = count(array_filter($calendarDays, fn($day) => !$day['is_weekend'] && !$day['is_holiday']));
        $dailyAttendance = ['male' => 0, 'female' => 0, 'total' => 0];
        $totalAttendanceScore = ['male' => 0, 'female' => 0, 'total' => 0];

        foreach ($studentSummaries as $summary) {
            $student = $enrolledStudents->firstWhere('id', $summary['student']['id']);
            $gender = strtolower($student->gender ?? 'male');
            $genderKey = $gender === 'female' ? 'female' : 'male';

            $presentDays = $summary['monthly_summary']['present_days'] ?? 0;
            $halfDays = $summary['monthly_summary']['half_days'] ?? 0;
            $attendanceScore = $presentDays + ($halfDays * 0.5);

            if ($totalDays > 0) {
                $dailyAvg = $attendanceScore / $totalDays;
                $dailyAttendance[$genderKey] += $dailyAvg;
                $dailyAttendance['total'] += $dailyAvg;
            }

            $totalAttendanceScore[$genderKey] += $attendanceScore;
            $totalAttendanceScore['total'] += $attendanceScore;
        }

        // Calculate percentage
        $totalSchoolDays = $totalDays * $enrolledStudents->count();
        $attendancePercentage = [
            'male' => $registered['male'] > 0 && $totalDays > 0 
                ? round(($totalAttendanceScore['male'] / ($totalDays * $registered['male'])) * 100, 2) 
                : 0,
            'female' => $registered['female'] > 0 && $totalDays > 0 
                ? round(($totalAttendanceScore['female'] / ($totalDays * $registered['female'])) * 100, 2) 
                : 0,
            'total' => $registered['total'] > 0 && $totalDays > 0 
                ? round(($totalAttendanceScore['total'] / ($totalDays * $registered['total'])) * 100, 2) 
                : 0
        ];

        // Get enrollment changes (dropped out, transferred)
        // Note: This is simplified - in a real system, you'd track enrollment status changes
        $droppedPrev = ['male' => 0, 'female' => 0, 'total' => 0];
        $droppedMonth = ['male' => 0, 'female' => 0, 'total' => 0];
        $transferredOutPrev = ['male' => 0, 'female' => 0, 'total' => 0];
        $transferredOutMonth = ['male' => 0, 'female' => 0, 'total' => 0];
        $transferredInPrev = ['male' => 0, 'female' => 0, 'total' => 0];
        $transferredInMonth = ['male' => 0, 'female' => 0, 'total' => 0];

        // Calculate cumulative
        $droppedCumulative = [
            'male' => $droppedPrev['male'] + $droppedMonth['male'],
            'female' => $droppedPrev['female'] + $droppedMonth['female'],
            'total' => $droppedPrev['total'] + $droppedMonth['total']
        ];
        $transferredOutCumulative = [
            'male' => $transferredOutPrev['male'] + $transferredOutMonth['male'],
            'female' => $transferredOutPrev['female'] + $transferredOutMonth['female'],
            'total' => $transferredOutPrev['total'] + $transferredOutMonth['total']
        ];
        $transferredInCumulative = [
            'male' => $transferredInPrev['male'] + $transferredInMonth['male'],
            'female' => $transferredInPrev['female'] + $transferredInMonth['female'],
            'total' => $transferredInPrev['total'] + $transferredInMonth['total']
        ];

        return [
            'registered' => $registered,
            'attendance_daily_avg' => [
                'male' => round($dailyAttendance['male'], 2),
                'female' => round($dailyAttendance['female'], 2),
                'total' => round($dailyAttendance['total'], 2)
            ],
            'attendance_percentage' => $attendancePercentage,
            'dropped_prev' => $droppedPrev,
            'dropped_month' => $droppedMonth,
            'dropped_cumulative' => $droppedCumulative,
            'transferred_out_prev' => $transferredOutPrev,
            'transferred_out_month' => $transferredOutMonth,
            'transferred_out_cumulative' => $transferredOutCumulative,
            'transferred_in_prev' => $transferredInPrev,
            'transferred_in_month' => $transferredInMonth,
            'transferred_in_cumulative' => $transferredInCumulative
        ];
    }
}