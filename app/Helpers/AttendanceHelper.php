<?php

namespace App\Helpers;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use Carbon\Carbon;

class AttendanceHelper {
    /**
     * Verify teacher has access to the schedule
     */

    public static function verifyScheduleAccess($scheduleId, $teacherId) {
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
        return Student::with(['user:id,email'])
            ->where('id', $studentId)
            ->where('section_id', $sectionId)
            ->where('enrollment_status', 'enrolled')
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
        return $attendances->groupBy(function($attendance) {
            return Carbon::parse($attendance->attendance_date)->format('Y-m');
        })->map(function($monthAttendances, $month) {
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
        return $attendances->map(function($attendance) {
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