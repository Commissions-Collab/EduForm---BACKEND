<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Quarter;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->input('academic_year_id');
        $section = $request->input('section_id');
        $quarter = $request->input('quarter_id');

        $perfectAttendance = $this->getPerfectAttendance($year, $section, $quarter);
        $honorRoll = $this->getHonorRoll($year, $section, $quarter);

        // Check if quarter is complete
        $quarterComplete = $this->isQuarterComplete($quarter);

        return response()->json([
            'perfect_attendance' => $perfectAttendance,
            'honor_roll' => $honorRoll,
            'quarter_complete' => $quarterComplete,
        ]);
    }

    public function preview($type, $studentId, $quarterId = null)
    {
        // Check if quarter is complete
        if (!$this->isQuarterComplete($quarterId)) {
            abort(403, 'Cannot preview certificate. The selected quarter has not ended yet.');
        }

        $student = Student::with('section.yearLevel', 'section.academicYear')->findOrFail($studentId);

        // Get academic year name
        $academicYear = $student->section->academicYear->name ?? 'N/A';

        if ($type === 'perfect_attendance') {
            // Check if attendance data is complete for the quarter
            if (!$this->isAttendanceComplete($studentId, $quarterId)) {
                abort(403, 'Cannot preview certificate. Attendance data is incomplete for this quarter.');
            }

            // Get attendances for student
            $attendances = Attendance::where('student_id', $studentId)
                ->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId))
                ->where('status', '!=', 'present')
                ->get();

            // If no non-present records = perfect attendance
            if ($attendances->count() > 0) {
                abort(403, 'Student does not have perfect attendance for this quarter.');
            }

            // Get quarters (label) the student has perfect attendance for
            $quarterNames = Attendance::where('student_id', $studentId)
                ->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId))
                ->select('quarter_id')
                ->distinct()
                ->pluck('quarter_id')
                ->map(fn($id) => Quarter::find($id)?->name ?? 'Unknown')
                ->join(', ');

            $data = [
                'quarters' => $quarterNames ?: 'All Quarters',
                'academic_year' => $academicYear,
            ];

            $pdf = Pdf::loadView('certificates.perfect_attendance', compact('student', 'data'));
            $pdf->setPaper('A4', 'landscape');
        }

        if ($type === 'honor_roll') {
            // Check if grades data is complete for the quarter
            if (!$this->isGradesComplete($studentId, $quarterId)) {
                abort(403, 'Cannot preview certificate. Grades data is incomplete for this quarter.');
            }

            // Get grades for the student
            $grades = Grade::where('student_id', $studentId)
                ->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId))
                ->pluck('grade');

            if ($grades->count() === 0) {
                abort(404, 'No grades found for this student in the selected quarter.');
            }

            $average = round($grades->avg(), 2);

            // Determine honor type
            $honorType = null;
            if ($average >= 98 && $average <= 100) $honorType = 'With Highest Honors';
            elseif ($average >= 95 && $average < 98) $honorType = 'With High Honors';
            elseif ($average >= 90 && $average < 95) $honorType = 'With Honors';

            if (!$honorType) {
                abort(403, 'Student does not qualify for honors.');
            }

            $quarterLabel = $quarterId ? Quarter::find($quarterId)?->name ?? 'N/A' : 'All Quarters';

            $data = [
                'honor_type' => $honorType,
                'grade_average' => $average,
                'quarter' => $quarterLabel,
                'academic_year' => $academicYear,
            ];

            $pdf = Pdf::loadView('certificates.honor_roll', compact('student', 'data'));
            $pdf->setPaper('A4', 'landscape');
        }

        return $pdf->stream("certificate-{$type}.pdf");
    }

    public function download($type, $studentId, $quarterId = null)
    {
        // Check if quarter is complete
        if (!$this->isQuarterComplete($quarterId)) {
            abort(403, 'Cannot download certificate. The selected quarter has not ended yet.');
        }

        $student = Student::with('section.yearLevel', 'section.academicYear')->findOrFail($studentId);

        // Get academic year name
        $academicYear = $student->section->academicYear->name ?? 'N/A';

        if ($type === 'perfect_attendance') {
            // Check if attendance data is complete for the quarter
            if (!$this->isAttendanceComplete($studentId, $quarterId)) {
                abort(403, 'Cannot download certificate. Attendance data is incomplete for this quarter.');
            }

            // Get attendances for student
            $attendances = Attendance::where('student_id', $studentId)
                ->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId))
                ->where('status', '!=', 'present')
                ->get();

            // If no non-present records = perfect attendance
            if ($attendances->count() > 0) {
                abort(403, 'Student does not have perfect attendance for this quarter.');
            }

            // Get quarters (label) the student has perfect attendance for
            $quarterNames = Attendance::where('student_id', $studentId)
                ->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId))
                ->select('quarter_id')
                ->distinct()
                ->pluck('quarter_id')
                ->map(fn($id) => Quarter::find($id)?->name ?? 'Unknown')
                ->join(', ');

            $data = [
                'quarters' => $quarterNames ?: 'All Quarters',
                'academic_year' => $academicYear,
            ];

            $pdf = Pdf::loadView('certificates.perfect_attendance', compact('student', 'data'));
            $pdf->setPaper('A4', 'landscape');
        }

        if ($type === 'honor_roll') {
            // Check if grades data is complete for the quarter
            if (!$this->isGradesComplete($studentId, $quarterId)) {
                abort(403, 'Cannot download certificate. Grades data is incomplete for this quarter.');
            }

            // Get grades for the student
            $grades = Grade::where('student_id', $studentId)
                ->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId))
                ->pluck('grade');

            if ($grades->count() === 0) {
                abort(404, 'No grades found for this student in the selected quarter.');
            }

            $average = round($grades->avg(), 2);

            // Determine honor type
            $honorType = null;
            if ($average >= 98 && $average <= 100) $honorType = 'With Highest Honors';
            elseif ($average >= 95 && $average < 98) $honorType = 'With High Honors';
            elseif ($average >= 90 && $average < 95) $honorType = 'With Honors';

            if (!$honorType) {
                abort(403, 'Student does not qualify for honors.');
            }

            $quarterLabel = $quarterId ? Quarter::find($quarterId)?->name ?? 'N/A' : 'All Quarters';

            $data = [
                'honor_type' => $honorType,
                'grade_average' => $average,
                'quarter' => $quarterLabel,
                'academic_year' => $academicYear,
            ];

            $pdf = Pdf::loadView('certificates.honor_roll', compact('student', 'data'));
            $pdf->setPaper('A4', 'landscape');
        }

        return $pdf->download("certificate-{$type}-{$studentId}.pdf");
    }

    public function filterHonorRoll(Request $request)
    {
        $year = $request->input('academic_year_id');
        $section = $request->input('section_id');
        $quarter = $request->input('quarter_id');
        $honorType = $request->input('honor_type');

        $all = $this->getHonorRoll($year, $section, $quarter);

        if ($honorType) {
            $filtered = collect($all)->filter(fn($q) => $q['honor_type'] === $honorType)->values();
        } else {
            $filtered = $all;
        }

        return response()->json($filtered);
    }

    public function printAll(Request $request)
    {
        $year = $request->input('academic_year_id');
        $section = $request->input('section_id');
        $quarter = $request->input('quarter_id');
        $type = $request->input('type'); // perfect_attendance or honor_roll

        // Check if quarter is complete
        if (!$this->isQuarterComplete($quarter)) {
            return response()->json(['error' => 'Cannot print certificates. The selected quarter has not ended yet.'], 403);
        }

        $students = Student::whereHas('enrollments', function ($q) use ($year, $section) {
            $q->where('academic_year_id', $year)
                ->where('section_id', $section);
        })->get();

        $qualified = [];

        foreach ($students as $student) {
            try {
                if ($type === 'perfect_attendance') {
                    // Check if attendance data is complete
                    if (!$this->isAttendanceComplete($student->id, $quarter)) {
                        continue;
                    }

                    // Get attendances for student
                    $attendances = Attendance::where('student_id', $student->id)
                        ->when($quarter, fn($q) => $q->where('quarter_id', $quarter))
                        ->where('status', '!=', 'present')
                        ->count();

                    if ($attendances === 0) {
                        // Get quarters (label) the student has perfect attendance for
                        $quarterNames = Attendance::where('student_id', $student->id)
                            ->when($quarter, fn($q) => $q->where('quarter_id', $quarter))
                            ->select('quarter_id')
                            ->distinct()
                            ->pluck('quarter_id')
                            ->map(fn($id) => Quarter::find($id)?->name ?? 'Unknown')
                            ->join(', ');

                        $qualified[] = [
                            'student' => $student,
                            'data' => [
                                'quarters' => $quarterNames ?: 'All Quarters',
                                'academic_year' => $student->enrollments->firstWhere('academic_year_id', $year)?->academicYear?->name ?? 'N/A',
                            ]
                        ];
                    }
                } else {
                    // Check if grades data is complete
                    if (!$this->isGradesComplete($student->id, $quarter)) {
                        continue;
                    }

                    $grades = Grade::where('student_id', $student->id)
                        ->when($quarter, fn($q) => $q->where('quarter_id', $quarter))
                        ->pluck('grade');

                    if ($grades->count() > 0) {
                        $average = round($grades->avg(), 2);

                        $honorType = null;
                        if ($average >= 98 && $average <= 100) $honorType = 'With Highest Honors';
                        elseif ($average >= 95 && $average < 98) $honorType = 'With High Honors';
                        elseif ($average >= 90 && $average < 95) $honorType = 'With Honors';

                        if ($honorType) {
                            $quarterLabel = $quarter ? Quarter::find($quarter)?->name ?? 'N/A' : 'All Quarters';
                            $qualified[] = [
                                'student' => $student,
                                'data' => [
                                    'honor_type' => $honorType,
                                    'grade_average' => $average,
                                    'quarter' => $quarterLabel,
                                    'academic_year' => $student->enrollments->firstWhere('academic_year_id', $year)?->academicYear?->name ?? 'N/A',
                                ]
                            ];
                        }
                    }
                }
            } catch (\Throwable $th) {
                continue;
            }
        }

        if (empty($qualified)) {
            return response()->json(['message' => 'No qualified students found.']);
        }

        $pdf = Pdf::loadView("certificates.batch_{$type}", compact('qualified'));
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download("all_{$type}_certificates.pdf");
    }

    private function getPerfectAttendance($yearId, $sectionId, $quarterId)
    {
        $students = Student::whereHas('enrollments', function ($query) use ($yearId, $sectionId) {
            $query->where('academic_year_id', $yearId)
                ->where('section_id', $sectionId);
        })->with([
            'grades' => function ($query) use ($quarterId) {
                $query->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId));
            }
        ])->get();

        $quarterComplete = $this->isQuarterComplete($quarterId);
        $perfect = [];

        foreach ($students as $student) {
            $attendances = $student->attendances;

            if ($quarterId) {
                // Only attendance in this quarter
                $attendances = $attendances->where('quarter_id', $quarterId);
            }

            if ($attendances->count() === 0) continue;

            // Check if all are present
            $isPerfect = $attendances->every(function ($record) {
                return in_array($record->status, ['present', 'excused', 'late']);
            });

            if ($isPerfect) {
                $quarters = $attendances->pluck('quarter_id')->unique();
                $attendanceComplete = $this->isAttendanceComplete($student->id, $quarterId);
                
                $perfect[] = [
                    'id' => $student->id,
                    'student_name' => $student->fullName(),
                    'quarters' => $quarters->map(fn($id) => Quarter::find($id)?->name ?? 'Unknown')->join(', '),
                    'can_generate' => $quarterComplete && $attendanceComplete,
                ];
            }
        }

        return $perfect;
    }

    private function getHonorRoll($yearId, $sectionId, $quarterId)
    {
        $students = Student::whereHas('enrollments', function ($query) use ($yearId, $sectionId) {
            $query->where('academic_year_id', $yearId)
                ->where('section_id', $sectionId);
        })->with([
            'grades' => function ($query) use ($quarterId) {
                $query->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId));
            }
        ])->get();

        $quarterName = $quarterId ? (Quarter::find($quarterId)?->name ?? 'Unknown') : 'All Quarters';
        $quarterComplete = $this->isQuarterComplete($quarterId);
        $honors = [];

        foreach ($students as $student) {
            $grades = $student->grades;

            if ($grades->count() === 0) continue;

            $average = $grades->avg('grade');

            $honor = null;
            if ($average >= 98 && $average <= 100) $honor = 'With Highest Honors';
            elseif ($average >= 95 && $average < 98) $honor = 'With High Honors';
            elseif ($average >= 90 && $average < 95) $honor = 'With Honors';

            if ($honor) {
                $gradesComplete = $this->isGradesComplete($student->id, $quarterId);
                
                $honors[] = [
                    'id' => $student->id,
                    'student_name' => $student->fullName(),
                    'grade_average' => round($average, 2),
                    'honor_type' => $honor,
                    'quarter' => $quarterName,
                    'can_generate' => $quarterComplete && $gradesComplete,
                ];
            }
        }

        return $honors;
    }

    /**
     * Check if a quarter is complete (end date has passed)
     */
    private function isQuarterComplete($quarterId)
    {
        if (!$quarterId) {
            return false;
        }

        $quarter = Quarter::find($quarterId);
        if (!$quarter) {
            return false;
        }

        return Carbon::now()->isAfter($quarter->end_date);
    }

    /**
     * Check if attendance data is complete for a student in a quarter
     */
    private function isAttendanceComplete($studentId, $quarterId)
    {
        if (!$quarterId) {
            return false;
        }

        $quarter = Quarter::find($quarterId);
        if (!$quarter) {
            return false;
        }

        // Check if quarter has ended and academic year has ended
        $now = Carbon::now();
        $quarterEnded = $now->isAfter($quarter->end_date);
        
        // Get academic year end date
        $academicYearEnded = $now->isAfter($quarter->academicYear->end_date ?? $quarter->end_date);
        
        // Both quarter and academic year should have ended for complete data
        if (!$quarterEnded || !$academicYearEnded) {
            return false;
        }

        // Check if student has attendance records for the quarter
        $attendanceCount = Attendance::where('student_id', $studentId)
            ->where('quarter_id', $quarterId)
            ->count();

        // Student should have at least some attendance records
        // You might want to add more sophisticated validation here
        // like checking against expected school days within the quarter period
        return $attendanceCount > 0;
    }

    /**
     * Check if grades data is complete for a student in a quarter
     */
    private function isGradesComplete($studentId, $quarterId)
    {
        if (!$quarterId) {
            return false;
        }

        $quarter = Quarter::find($quarterId);
        if (!$quarter) {
            return false;
        }

        // Check if quarter has ended and academic year has ended
        $now = Carbon::now();
        $quarterEnded = $now->isAfter($quarter->end_date);
        
        // Get academic year end date
        $academicYearEnded = $now->isAfter($quarter->academicYear->end_date ?? $quarter->end_date);
        
        // Both quarter and academic year should have ended for complete data
        if (!$quarterEnded || !$academicYearEnded) {
            return false;
        }

        // Get student's enrollment to find their grade level
        $enrollment = \App\Models\Enrollment::where('student_id', $studentId)
            ->where('academic_year_id', $quarter->academic_year_id)
            ->first();

        if (!$enrollment) {
            return false;
        }

        // Get expected subjects for the student's grade level
        // Assuming you have a relationship between year_levels and subjects
        // You might need to adjust this based on your actual relationship structure
        $expectedSubjectsCount = \App\Models\Subject::whereHas('yearLevels', function($query) use ($enrollment) {
            $query->where('year_level_id', $enrollment->grade_level);
        })->where('is_active', true)->count();

        // If no relationship exists, you might have a direct foreign key or pivot table
        // Alternative approach if subjects are directly related to year_levels:
        // $expectedSubjectsCount = \App\Models\Subject::where('year_level_id', $enrollment->grade_level)
        //     ->where('is_active', true)->count();

        // Get actual grades count for the student in this quarter
        $actualGradesCount = Grade::where('student_id', $studentId)
            ->where('quarter_id', $quarterId)
            ->count();

        // Check if student has grades for all expected subjects
        // You might want to allow some flexibility here (e.g., 90% completion)
        return $actualGradesCount >= $expectedSubjectsCount && $expectedSubjectsCount > 0;
    }
}