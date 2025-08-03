<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Quarter;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->input('academic_year_id');
        $section = $request->input('section_id');
        $quarter = $request->input('quarter_id');

        $perfectAttendance = $this->getPerfectAttendance($year, $section, $quarter);
        $honorRoll = $this->getHonorRoll($year, $section, $quarter);

        return response()->json([
            'perfect_attendance' => $perfectAttendance,
            'honor_roll' => $honorRoll,
        ]);
    }

    public function preview($type, $studentId, $quarterId = null)
    {
        $student = Student::with('section.yearLevel', 'section.academicYear')->findOrFail($studentId);

        // Get academic year name
        $academicYear = $student->section->academicYear->name ?? 'N/A';

        if ($type === 'perfect_attendance') {
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
        $student = Student::with('section.yearLevel', 'section.academicYear')->findOrFail($studentId);

        // Get academic year name
        $academicYear = $student->section->academicYear->name ?? 'N/A';

        if ($type === 'perfect_attendance') {
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

        $students = Student::whereHas('enrollments', function ($q) use ($year, $section) {
            $q->where('academic_year_id', $year)
                ->where('section_id', $section);
        })->get();

        $qualified = [];

        foreach ($students as $student) {
            try {
                if ($type === 'perfect_attendance') {
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
                $perfect[] = [
                    'id' => $student->id,
                    'student_name' => $student->fullName(),
                    'quarters' => $quarters->map(fn($id) => Quarter::find($id)?->name ?? 'Unknown')->join(', '),
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
                $honors[] = [
                    'id' => $student->id,
                    'student_name' => $student->fullName(),
                    'grade_average' => round($average, 2),
                    'honor_type' => $honor,
                    'quarter' => $quarterName
                ];
            }
        }

        return $honors;
    }
}
