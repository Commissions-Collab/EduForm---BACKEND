<?php

namespace App\Helpers;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceHelper
{
    /**
     * Check if student has reached tardiness limit for a subject
     * If student has been late 4 times already, the 5th late should become absent
     * 
     * @param int $studentId
     * @param int $scheduleId
     * @param int $academicYearId
     * @param int|null $quarterId
     * @param string|null $attendanceDate
     * @return array ['should_convert' => bool, 'late_count' => int, 'message' => string]
     */
    public static function checkTardinessLimit(
        int $studentId,
        int $scheduleId,
        int $academicYearId,
        ?int $quarterId = null,
        ?string $attendanceDate = null
    ): array {
        try {
            // Get the schedule to find the subject
            $schedule = Schedule::with('subject')->find($scheduleId);
            
            if (!$schedule) {
                return [
                    'should_convert' => false,
                    'late_count' => 0,
                    'message' => 'Schedule not found',
                    'error' => 'Invalid schedule'
                ];
            }

            $subjectId = $schedule->subject_id;

            // Count existing "late" records for this student and subject in current academic year
            $query = Attendance::whereHas('schedule', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            })
                ->where('student_id', $studentId)
                ->where('academic_year_id', $academicYearId)
                ->where('status', 'late');

            // Optionally filter by quarter if provided
            if ($quarterId) {
                $query->where('quarter_id', $quarterId);
            }

            // Exclude current date to count previous late instances only
            if ($attendanceDate) {
                $query->where('attendance_date', '!=', $attendanceDate);
            }

            $lateCount = $query->count();

            // If student already has 4 or more "late" records, convert to absent
            $shouldConvert = $lateCount >= 4;

            $subjectName = $schedule->subject->name ?? 'this subject';
            $message = $shouldConvert 
                ? "Student has been late {$lateCount} times for {$subjectName}. This 5th late is automatically marked as ABSENT."
                : "Student has been late {$lateCount} times for {$subjectName}.";

            return [
                'should_convert' => $shouldConvert,
                'late_count' => $lateCount,
                'message' => $message,
                'subject_name' => $subjectName,
                'threshold_reached' => $shouldConvert
            ];
        } catch (\Exception $e) {
            Log::error('Tardiness limit check failed: ' . $e->getMessage(), [
                'student_id' => $studentId,
                'schedule_id' => $scheduleId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'should_convert' => false,
                'late_count' => 0,
                'message' => 'Unable to check tardiness limit',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get tardiness statistics for a student across all subjects
     * 
     * @param int $studentId
     * @param int $academicYearId
     * @param int|null $quarterId
     * @return array
     */
    public static function getTardinessStats(
        int $studentId,
        int $academicYearId,
        ?int $quarterId = null
    ): array {
        try {
            $query = DB::table('attendances')
                ->join('schedules', 'attendances.schedule_id', '=', 'schedules.id')
                ->join('subjects', 'schedules.subject_id', '=', 'subjects.id')
                ->select(
                    'subjects.id as subject_id',
                    'subjects.name as subject_name',
                    DB::raw('SUM(CASE WHEN attendances.status = "late" THEN 1 ELSE 0 END) as late_count'),
                    DB::raw('SUM(CASE WHEN attendances.status = "absent" AND attendances.remarks LIKE "%5th late%" THEN 1 ELSE 0 END) as converted_count')
                )
                ->where('attendances.student_id', $studentId)
                ->where('attendances.academic_year_id', $academicYearId);

            if ($quarterId) {
                $query->where('attendances.quarter_id', $quarterId);
            }

            $stats = $query->groupBy('subjects.id', 'subjects.name')
                ->get();

            return [
                'subjects' => $stats->map(function ($stat) {
                    $lateCount = (int) $stat->late_count;
                    return [
                        'subject_id' => $stat->subject_id,
                        'subject_name' => $stat->subject_name,
                        'late_count' => $lateCount,
                        'converted_count' => (int) $stat->converted_count,
                        'at_risk' => $lateCount >= 3,
                        'remaining_lates' => max(0, 4 - $lateCount)
                    ];
                })->toArray(),
                'total_late' => $stats->sum('late_count'),
                'total_converted' => $stats->sum('converted_count')
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get tardiness stats: ' . $e->getMessage(), [
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'subjects' => [],
                'total_late' => 0,
                'total_converted' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Apply tardiness rule to attendance record
     * 
     * @param array $attendanceData
     * @return array Modified attendance data
     */
    public static function applyTardinessRule(array $attendanceData): array
    {
        // Only apply if status is "late"
        if (strtolower($attendanceData['status']) !== 'late') {
            return $attendanceData;
        }

        $check = self::checkTardinessLimit(
            $attendanceData['student_id'],
            $attendanceData['schedule_id'],
            $attendanceData['academic_year_id'],
            $attendanceData['quarter_id'] ?? null,
            $attendanceData['attendance_date']
        );

        if ($check['should_convert']) {
            // Convert to absent
            $attendanceData['status'] = 'absent';
            
            // Add or append to remarks
            $tardinessNote = "Automatically marked ABSENT - 5th late for {$check['subject_name']}";
            $attendanceData['remarks'] = !empty($attendanceData['remarks']) 
                ? $attendanceData['remarks'] . ' | ' . $tardinessNote
                : $tardinessNote;
            
            // Add metadata for tracking
            $attendanceData['tardiness_conversion'] = true;
            $attendanceData['tardiness_message'] = $check['message'];
            
            // Log the conversion
            Log::info('Tardiness rule applied - 5th late converted to absent', [
                'student_id' => $attendanceData['student_id'],
                'schedule_id' => $attendanceData['schedule_id'],
                'subject' => $check['subject_name'],
                'late_count' => $check['late_count'],
                'date' => $attendanceData['attendance_date']
            ]);
        }

        return $attendanceData;
    }

    /**
     * Verify teacher has access to the schedule
     */
    public static function verifyScheduleAccess($scheduleId, $teacherId)
    {
        return Schedule::with(['subject', 'section', 'section.yearLevel'])
            ->where('id', $scheduleId)
            ->where('teacher_id', $teacherId)
            ->first();
    }

    /**
     * Verify student belongs to the section and is enrolled
     */
    public static function verifyStudentAccess($studentId, $sectionId)
    {
        return Student::with(['enrollments'])->whereHas('enrollments', function ($query) use ($sectionId) {
            $query->where('enrollment_status', 'enrolled')
                ->where('section_id', $sectionId);
        })
            ->first();
    }

    /**
     * Get date range for attendance query
     */
    public static function getDateRange($request, $academicYear): array
    {
        $startDate = $request->get('start_date')
            ? Carbon::parse($request->get('start_date'))
            : Carbon::parse($academicYear->start_date);

        $endDate = $request->get('end_date')
            ? Carbon::parse($request->get('end_date'))
            : Carbon::parse($academicYear->end_date);

        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }

    /**
     * Get attendance records for student
     */
    public static function getAttendanceRecords($studentId, $scheduleId, $academicYearId, $dateRange)
    {
        return Attendance::with(['recordedBy:id,email', 'recordedBy.teacher:id,user_id,first_name,last_name'])
            ->where('student_id', $studentId)
            ->where('schedule_id', $scheduleId)
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('attendance_date', [
                $dateRange['start']->format('Y-m-d'),
                $dateRange['end']->format('Y-m-d')
            ])
            ->orderBy('attendance_date', 'desc')
            ->get();
    }

    /**
     * Calculate attendance statistics
     */
    public static function calculateAttendanceSummary($attendances): array
    {
        $totalClasses = $attendances->count();
        $presentCount = $attendances->where('status', 'present')->count();
        $absentCount = $attendances->where('status', 'absent')->count();
        $lateCount = $attendances->where('status', 'late')->count();
        $excusedCount = $attendances->where('status', 'excused')->count();

        $attendedCount = $presentCount + $lateCount;
        $attendancePercentage = $totalClasses > 0 ? round(($attendedCount / $totalClasses) * 100, 2) : 0;

        return [
            'total_classes' => $totalClasses,
            'present' => $presentCount,
            'absent' => $absentCount,
            'late' => $lateCount,
            'excused' => $excusedCount,
            'attended' => $attendedCount,
            'attendance_percentage' => $attendancePercentage,
            'absent_days' => $absentCount,
            'tardiness_count' => $lateCount
        ];
    }

    /**
     * Get monthly attendance breakdown
     */
    public static function getMonthlyBreakdown($attendances)
    {
        return $attendances->groupBy(function ($attendance) {
            return Carbon::parse($attendance->attendance_date)->format('Y-m');
        })->map(function ($monthAttendances, $month) {
            $monthData = $monthAttendances->groupBy('status');
            $presentCount = $monthData->get('present', collect())->count();
            $lateCount = $monthData->get('late', collect())->count();
            $totalCount = $monthAttendances->count();

            return [
                'month' => $month,
                'month_name' => Carbon::parse($month . '-01')->format('F Y'),
                'total' => $totalCount,
                'present' => $presentCount,
                'absent' => $monthData->get('absent', collect())->count(),
                'late' => $lateCount,
                'excused' => $monthData->get('excused', collect())->count(),
                'attendance_rate' => $totalCount > 0
                    ? round((($presentCount + $lateCount) / $totalCount) * 100, 2)
                    : 0
            ];
        })->values();
    }

    /**
     * Format attendance records for response
     */
    public static function formatAttendanceRecords($attendances)
    {
        return $attendances->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'date' => $attendance->attendance_date,
                'day_of_week' => Carbon::parse($attendance->attendance_date)->format('l'),
                'status' => $attendance->status,
                'time_in' => $attendance->time_in,
                'time_out' => $attendance->time_out,
                'remarks' => $attendance->remarks,
                'recorded_by' => self::formatRecordedByData($attendance->recordedBy),
                'recorded_at' => $attendance->recorded_at->format('Y-m-d H:i:s'),
                'is_recent' => $attendance->recorded_at->diffInDays(now()) <= 7
            ];
        });
    }

    /**
     * Format student data for response
     */
    public static function formatStudentData($student): array
    {
        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'lrn' => $student->lrn,
            'full_name' => trim($student->first_name . ' ' . $student->middle_name . ' ' . $student->last_name),
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'photo' => $student->photo,
            'email' => $student->user->email ?? null
        ];
    }

    /**
     * Format subject data for response
     */
    public static function formatSubjectData($subject): array
    {
        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code
        ];
    }

    /**
     * Format section data for response
     */
    public static function formatSectionData($section): array
    {
        return [
            'id' => $section->id,
            'name' => $section->name,
            'year_level' => $section->yearLevel->name
        ];
    }

    /**
     * Format academic year data for response
     */
    public static function formatAcademicYearData($academicYear): array
    {
        return [
            'id' => $academicYear->id,
            'name' => $academicYear->name
        ];
    }

    /**
     * Format recorded by data for response
     */
    public static function formatRecordedByData($recordedBy): array
    {
        return [
            'id' => $recordedBy->id,
            'name' => $recordedBy->teacher
                ? trim($recordedBy->teacher->first_name . ' ' . $recordedBy->teacher->last_name)
                : $recordedBy->email
        ];
    }
}