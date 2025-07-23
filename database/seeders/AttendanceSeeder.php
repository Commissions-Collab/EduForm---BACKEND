<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        $schedules = Schedule::where('academic_year_id', $currentYear->id)->get();
        $teachers = User::where('role', 'teacher')->get();
        
        // Generate attendance for the last 2 months
        $startDate = Carbon::now()->subMonths(2)->startOfMonth();
        $endDate = Carbon::now();
        
        foreach ($schedules as $schedule) {
            $students = Student::where('section_id', $schedule->section_id)->get();
            
            // Generate dates for this schedule based on day_of_week
            $dates = $this->getScheduleDates($schedule->day_of_week, $startDate, $endDate);
            
            foreach ($dates as $date) {
                foreach ($students as $student) {
                    // 85% attendance rate on average
                    $attendanceStatuses = ['present', 'present', 'present', 'present', 'absent', 'late'];
                    $status = $attendanceStatuses[array_rand($attendanceStatuses)];
                    
                    Attendance::create([
                        'student_id' => $student->id,
                        'schedule_id' => $schedule->id,
                        'academic_year_id' => $currentYear->id,
                        'attendance_date' => $date,
                        'status' => $status,
                        'time_in' => $status === 'late' ? Carbon::parse($schedule->start_time)->addMinutes(rand(5, 30))->format('H:i:s') : null,
                        'time_out' => rand(0, 10) === 0 ? Carbon::parse($schedule->end_time)->subMinutes(rand(5, 15))->format('H:i:s') : null,
                        'remarks' => $status === 'absent' ? (rand(0, 2) === 0 ? 'Sick' : null) : null,
                        'recorded_by' => $teachers->random()->id,
                        'recorded_at' => Carbon::parse($date)->addHours(rand(8, 16))->addMinutes(rand(0, 59)),
                    ]);
                }
            }
        }
        
        // Generate attendance summaries
        $this->generateAttendanceSummaries();
    }

    private function getScheduleDates($dayOfWeek, $startDate, $endDate)
    {
        $dates = [];
        $dayMap = [
            'Monday' => Carbon::MONDAY,
            'Tuesday' => Carbon::TUESDAY,
            'Wednesday' => Carbon::WEDNESDAY,
            'Thursday' => Carbon::THURSDAY,
            'Friday' => Carbon::FRIDAY,
        ];
        
        $current = $startDate->copy()->next($dayMap[$dayOfWeek]);
        
        while ($current->lte($endDate)) {
            $dates[] = $current->format('Y-m-d');
            $current->addWeek();
        }
        
        return $dates;
    }
    
    private function generateAttendanceSummaries()
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        $students = Student::all();
        
        foreach ($students as $student) {
            $subjects = Schedule::where('section_id', $student->section_id)
                               ->where('academic_year_id', $currentYear->id)
                               ->with('subject')
                               ->get()
                               ->pluck('subject')
                               ->unique('id');
            
            foreach ($subjects as $subject) {
                // Generate summaries for the last 2 months
                for ($month = 1; $month <= 2; $month++) {
                    $monthDate = Carbon::now()->subMonths($month - 1);
                    
                    $attendances = Attendance::where('student_id', $student->id)
                                           ->whereHas('schedule', function($query) use ($subject) {
                                               $query->where('subject_id', $subject->id);
                                           })
                                           ->whereYear('attendance_date', $monthDate->year)
                                           ->whereMonth('attendance_date', $monthDate->month)
                                           ->get();
                    
                    if ($attendances->count() > 0) {
                        $totalClasses = $attendances->count();
                        $presentCount = $attendances->where('status', 'present')->count();
                        $absentCount = $attendances->where('status', 'absent')->count();
                        $lateCount = $attendances->where('status', 'late')->count();
                        $excusedCount = $attendances->where('status', 'excused')->count();
                        $attendancePercentage = ($presentCount / $totalClasses) * 100;
                        
                        AttendanceSummary::create([
                            'student_id' => $student->id,
                            'subject_id' => $subject->id,
                            'academic_year_id' => $currentYear->id,
                            'month' => $monthDate->month,
                            'year' => $monthDate->year,
                            'total_classes' => $totalClasses,
                            'present_count' => $presentCount,
                            'absent_count' => $absentCount,
                            'late_count' => $lateCount,
                            'excused_count' => $excusedCount,
                            'attendance_percentage' => round($attendancePercentage, 2),
                        ]);
                    }
                }
            }
        }
    }
}
