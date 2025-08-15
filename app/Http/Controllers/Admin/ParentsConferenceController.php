<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Quarter;
use App\Models\SectionAdvisor;
use App\Models\StudentBmi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParentsConferenceController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $this->getAuthenticatedTeacher();

        $currentYear = $this->getCurrentAcademicYear();

        $advisor = $this->getAdvisor($teacher->id, $currentYear->id);

        $enrolledStudents = Enrollment::with(['student'])
            ->where('section_id', $advisor->section_id)
            ->where('academic_year_id', $currentYear->id)
            ->get();

        $students = $enrolledStudents->map(function ($enrollment) {
            $studentUser = $enrollment->student;

            $grades = Grade::where('student_id', $studentUser->id)
                ->pluck('grade');

            $average = $grades->count() > 0 ? round($grades->avg(), 2) : null;

            $status = is_null($average) ? 'No Grades' : $this->getStatus($average);

            return [
                'id' => $studentUser->id,
                'name' => $studentUser->first_name . " " . $studentUser->middle_name . " " . $studentUser->last_name,
                'guardian' => $studentUser->parent_guardian_name,
                'status' => $status
            ];
        });

        return response()->json([
            'section' => $advisor->section->name,
            'students' => $students
        ]);
    }

    public function showStudentProfile($studentId)
    {
        $teacher = $this->getAuthenticatedTeacher();
        $currentYear = $this->getCurrentAcademicYear();
        $advisor = $this->getAdvisor($teacher->id, $currentYear->id);

        $enrollment = Enrollment::with(['student'])
            ->where('section_id', $advisor->section_id)
            ->where('academic_year_id', $currentYear->id)
            ->where('student_id', $studentId)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Student not found or not enrolled in your section.'], 404);
        }

        $studentUser = $enrollment->student;

        $bmiRecords = StudentBmi::where('student_id', $studentUser->id)
            ->where('academic_year_id', $currentYear->id)
            ->orderBy('quarter_id')
            ->get()
            ->map(function ($record) {
                return [
                    'quarter_id' => $record->quarter_id,
                    'height_cm' => $record->height_cm,
                    'weight_kg' => $record->weight_kg,
                    'bmi' => $record->bmi,
                    'bmi_category' => $record->bmi_category,
                    'remarks' => $record->remarks,
                    'recorded_at' => $record->recorded_at,
                ];
            });

        $grades = Grade::with('subject')
            ->select('subject_id', DB::raw('AVG(grade) as average_grade'))
            ->where('student_id', $studentId)
            ->groupBy('subject_id')
            ->get()
            ->map(function ($item) {
                return [
                    'subject' => $item->subject->name ?? 'Unknown Subject',
                    'average_grade' => round($item->average_grade, 2),
                ];
            });

        $attendanceRecords = Attendance::where('student_id', $studentId)
            ->where('academic_year_id', $currentYear->id)
            ->get();

        $totalDays = $attendanceRecords->count();
        $present = $attendanceRecords->where('status', 'present')->count();
        $absent = $attendanceRecords->where('status', 'absent')->count();
        $late = $attendanceRecords->where('status', 'late')->count();

        $attendanceSummary = [
            'present_percent' => $totalDays ? round(($present / $totalDays) * 100) : 0,
            'absent_percent' => $totalDays ? round(($absent / $totalDays) * 100) : 0,
            'late_percent' => $totalDays ? round(($late / $totalDays) * 100) : 0,
            'recent_absents' => $attendanceRecords->where('status', 'absent')
                ->sortByDesc('attendance_date')
                ->take(5)
                ->values()
        ];

        return response()->json([
            'section' => $advisor->section->name,
            'student' => [
                'id' => $studentUser->id,
                'name' => $studentUser->first_name . " " . $studentUser->middle_name . " " . $studentUser->last_name,
                'student_id' => $studentUser->student_id,
                'guardian' => $studentUser->parent_guardian_name,
                'guardian_email' => $studentUser->parent_guardian_email,
                'guardian_phone' => $studentUser->parent_guardian_phone,
                'grades' => $grades,
                'attendance_summary' => $attendanceSummary,
                'bmi_records' => $bmiRecords
            ]
        ]);
    }

    public function printStudentReportCard($studentId)
    {
        $teacher = $this->getAuthenticatedTeacher();
        $currentYear = $this->getCurrentAcademicYear();
        $advisor = $this->getAdvisor($teacher->id, $currentYear->id);
        $student = $this->getStudentFromAdvisorSection($advisor->section_id, $currentYear->id, $studentId);

        $quarters = $this->getAllQuarters($currentYear->id);
        $grades = $this->getStudentGrades($student->id, $currentYear->id, $quarters->pluck('id'));

        $groupedGrades = $this->groupGradesPerQuarter($grades);
        $quarterAverages = $this->computeQuarterAverages($groupedGrades);
        $finalAverage = $this->computeFinalAverage($quarterAverages);

        $quartersData = $this->transformQuarterData($quarters, $groupedGrades, $quarterAverages);

        $pdf = Pdf::loadView('pdf.student-report-card', [
            'student' => $student->first_name . " " . $student->middle_name . " " . $student->last_name,
            'student_id' => $student->id,
            'section' => $advisor->section->name,
            'quarters' => $quartersData,
            'final_average' => $finalAverage,
        ]);

        $pdf->setPaper('A4', 'landscape');
        return $pdf->download("Report_Card_{$student->last_name}.pdf");
    }

    public function printAllStudentReportCards()
    {
        $teacher = $this->getAuthenticatedTeacher();
        $currentYear = $this->getCurrentAcademicYear();
        $advisor = $this->getAdvisor($teacher->id, $currentYear->id);
        $quarters = $this->getAllQuarters($currentYear->id);

        $enrollments = Enrollment::with('student')
            ->where('section_id', $advisor->section_id)
            ->where('academic_year_id', $currentYear->id)
            ->get();

        $studentReports = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;

            $grades = $this->getStudentGrades($student->id, $currentYear->id, $quarters->pluck('id'));

            $groupedGrades = $this->groupGradesPerQuarter($grades);
            $quarterAverages = $this->computeQuarterAverages($groupedGrades);
            $finalAverage = $this->computeFinalAverage($quarterAverages);

            $quartersData = $this->transformQuarterData($quarters, $groupedGrades, $quarterAverages);

            $studentReports[] = [
                'student' => $student->first_name . " " . $student->middle_name . " " . $student->last_name,
                'student_id' => $student->id,
                'section' => $advisor->section->name,
                'quarters' => $quartersData,
                'final_average' => $finalAverage,
            ];
        }

        $pdf = Pdf::loadView('pdf.all-student-report-cards', [
            'students' => $studentReports,
        ]);

        $pdf->setPaper('A4', 'landscape');
        return $pdf->download("All_Report_Cards_{$advisor->section->name}.pdf");
    }

    private function getStatus($average)
    {
        if ($average >= 90) return 'Excellent';
        if ($average >= 85) return 'Good Standing';
        if ($average >= 75) return 'At Risk';
        return 'Critical';
    }

    private function getAuthenticatedTeacher()
    {
        $teacher = Auth::user()->teacher;
        if (!$teacher) {
            abort(404, 'Teacher profile not found.');
        }
        return $teacher;
    }

    private function getCurrentAcademicYear()
    {
        $year = AcademicYear::where('is_current', 1)->first();
        if (!$year) {
            abort(404, 'Active academic year not found.');
        }
        return $year;
    }

    private function getAdvisor($teacherId, $academicYearId)
    {
        $advisor = SectionAdvisor::where('teacher_id', $teacherId)
            ->where('academic_year_id', $academicYearId)
            ->with('section')
            ->first();

        if (!$advisor) {
            abort(403, 'You are not an adviser for any section this year.');
        }
        return $advisor;
    }

    private function getStudentFromAdvisorSection($sectionId, $academicYearId, $studentId)
    {
        $enrollment = Enrollment::with('student')
            ->where('section_id', $sectionId)
            ->where('academic_year_id', $academicYearId)
            ->where('student_id', $studentId)
            ->first();

        if (!$enrollment) {
            abort(404, 'Student not found in your advisory section.');
        }
        return $enrollment->student;
    }

    private function getAllQuarters($academicYearId)
    {
        return Quarter::where('academic_year_id', $academicYearId)
            ->orderBy('start_date')
            ->get();
    }

    private function getStudentGrades($studentId, $academicYearId, $quarterIds)
    {
        return Grade::with(['subject', 'quarter'])
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('quarter_id', $quarterIds)
            ->get();
    }

    private function groupGradesPerQuarter($grades)
    {
        return $grades->groupBy('quarter_id')->map(function ($gradesInQuarter) {
            return $gradesInQuarter->groupBy('subject_id')->map(function ($gradesPerSubject) {
                return [
                    'subject' => optional($gradesPerSubject->first()->subject)->name ?? 'Unknown',
                    'average' => round($gradesPerSubject->avg('grade'), 2),
                ];
            })->values();
        });
    }

    private function computeQuarterAverages($gradesPerQuarter)
    {
        $averages = [];
        foreach ($gradesPerQuarter as $quarterId => $subjects) {
            $subjectAverages = collect($subjects)->pluck('average');
            $averages[$quarterId] = round($subjectAverages->avg(), 2);
        }
        return $averages;
    }

    private function computeFinalAverage($quarterAverages)
    {
        return count($quarterAverages)
            ? round(collect($quarterAverages)->avg(), 2)
            : null;
    }

    private function transformQuarterData($quarters, $gradesPerQuarter, $quarterAverages)
    {
        return $quarters->map(function ($quarter) use ($gradesPerQuarter, $quarterAverages) {
            return [
                'quarter' => $quarter->name,
                'grades' => $gradesPerQuarter[$quarter->id] ?? [],
                'quarter_average' => $quarterAverages[$quarter->id] ?? null,
            ];
        });
    }
}
