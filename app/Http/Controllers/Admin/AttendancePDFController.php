<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Quarter;
use App\Models\SectionAdvisor;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendancePDFController extends Controller
{
    /**
     * Export Quarterly Attendance Summary as PDF
     */
    public function exportQuarterlyAttendancePDF($sectionId, Request $request)
    {
        try {
            $teacher = Auth::user()->teacher;
            $academicYear = $this->getCurrentAcademicYear($request->get('academic_year_id'));
            $quarterId = $request->get('quarter_id');

            $accessError = $this->requireAdvisorAccess($sectionId, $teacher->id, $academicYear->id);

            if ($accessError) {
                return response()->json($accessError->getData(), $accessError->getStatusCode());
            }

            // Get quarter
            $quarter = Quarter::where('id', $quarterId)
                ->where('academic_year_id', $academicYear->id)
                ->first();

            if (!$quarter) {
                return response()->json(['message' => 'Quarter not found'], 404);
            }

            // Get section and advisor info
            $sectionAdvisor = $this->getSectionAdvisorWithDetails($sectionId, $teacher->id, $academicYear->id);
            $section = $sectionAdvisor->section;

            // Get attendances per student in section for this quarter
            $students = $section->students()->orderBy('last_name')->get();

            $formattedSummaries = $students->map(function ($student) use ($quarterId) {
                $attendances = Attendance::where('student_id', $student->id)
                    ->where('quarter_id', $quarterId)
                    ->get();

                $present = $attendances->where('status', 'present')->count();
                $absent = $attendances->where('status', 'absent')->count();
                $late = $attendances->where('status', 'late')->count();
                $excused = $attendances->where('status', 'excused')->count();
                $total = $attendances->count();

                return [
                    'student' => [
                        'id' => $student->id,
                        'student_id' => $student->student_id,
                        'lrn' => $student->lrn,
                        'full_name' => trim($student->first_name . ' ' . $student->middle_name . ' ' . $student->last_name),
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name
                    ],
                    'attendance_data' => [
                        'total_school_days' => $total,
                        'present_days' => $present,
                        'absent_days' => $absent,
                        'late_days' => $late,
                        'excused_days' => $excused,
                        'half_days' => 0, // Optional logic if you track this
                        'attendance_percentage' => $total ? round(($present / $total) * 100, 2) : 0,
                        'tardiness_percentage' => $total ? round(($late / $total) * 100, 2) : 0,
                        'attendance_status' => $absent > ($total * 0.1) ? 'Needs Improvement' : 'Good'
                    ],
                    'remarks' => null // Optional if you plan to include remarks
                ];
            });

            // Prepare data for PDF
            $pdfData = [
                'title' => 'Quarterly Attendance Summary',
                'school_info' => $this->getSchoolInfo(),
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'year_level' => $section->yearLevel->name
                ],
                'advisor' => [
                    'name' => trim($sectionAdvisor->teacher->first_name . ' ' . $sectionAdvisor->teacher->last_name),
                    'email' => $sectionAdvisor->teacher->user->email
                ],
                'academic_year' => [
                    'name' => $academicYear->name
                ],
                'quarter' => [
                    'name' => $quarter->name,
                    'quarter_number' => $quarter->quarter_number,
                    'start_date' => Carbon::parse($quarter->start_date)->format('F j, Y'),
                    'end_date' => Carbon::parse($quarter->end_date)->format('F j, Y'),
                    'duration_days' => $quarter->getDurationInDays(),
                    'school_days' => $quarter->getSchoolDaysCount()
                ],
                'summaries' => $formattedSummaries,
                'class_statistics' => $this->calculateQuarterlyClassStatistics($formattedSummaries->toArray()),
                'generated_at' => now()->format('F j, Y \a\t g:i A'),
                'generated_by' => $teacher->user->name ?? $teacher->user->email
            ];

            // Generate PDF
            $pdf = Pdf::loadView('pdf.quarterly-attendance-summary', $pdfData);
            $pdf->setPaper('A4', 'portrait');

            $fileName = "Quarterly_Attendance_Summary_{$section->name}_{$quarter->name}_{$academicYear->name}.pdf";
            $fileName = str_replace(' ', '_', $fileName);

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate quarterly attendance PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public static function requireAdvisorAccess($sectionId, $teacherId, $academicYearId)
    {
        $advisor = SectionAdvisor::where('section_id', $sectionId)
            ->where('teacher_id', $teacherId)
            ->where('academic_year_id', $academicYearId)
            ->first();

        if (!$advisor) {
            return response()->json([
                'message' => 'Unauthorized access. You are not the section advisor for this section in the selected academic year.'
            ], 403);
        }

        return null;
    }

    private function getSchoolInfo(): array
    {
        return [
            'name' => config('app.school_name', 'School Name'),
            'address' => config('app.school_address', 'School Address'),
            'phone' => config('app.school_phone', 'Phone Number'),
            'email' => config('app.school_email', 'school@email.com'),
            'logo' => public_path('images/school-logo.png')
        ];
    }

    private function calculateQuarterlyClassStatistics($summaries): array
    {
        $totalStudents = count($summaries);
        if ($totalStudents === 0) {
            return [
                'total_students' => 0,
                'average_attendance' => 0,
                'perfect_attendance' => 0,
                'below_threshold' => 0
            ];
        }

        $totalAttendance = 0;
        $perfectAttendance = 0;
        $belowThreshold = 0;
        $threshold = 85;

        foreach ($summaries as $summary) {
            $attendance = $summary['attendance_data']['attendance_percentage'];
            $totalAttendance += $attendance;

            if ($attendance == 100) {
                $perfectAttendance++;
            }

            if ($attendance < $threshold) {
                $belowThreshold++;
            }
        }

        return [
            'total_students' => $totalStudents,
            'average_attendance' => round($totalAttendance / $totalStudents, 2),
            'perfect_attendance' => $perfectAttendance,
            'below_threshold' => $belowThreshold,
            'threshold_percentage' => $threshold
        ];
    }

    private function getCurrentAcademicYear($academicYearId = null)
    {
        // Implementation should match your other controllers
        // This is a placeholder - implement based on your existing logic
        return AcademicYear::find($academicYearId)
            ?? AcademicYear::where('is_current', true)->first();
    }

    private function getSectionAdvisorWithDetails($sectionId, $teacherId, $academicYearId)
    {
        // Implementation should match your other controllers
        // This is a placeholder - implement based on your existing logic
        return SectionAdvisor::with(['section.yearLevel', 'teacher.user'])
            ->where('section_id', $sectionId)
            ->where('teacher_id', $teacherId)
            ->where('academic_year_id', $academicYearId)
            ->first();
    }
}
