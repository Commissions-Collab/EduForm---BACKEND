<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Quarter;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        try {
            Log::info('Certificate index called', [
                'params' => $request->all(),
                'user' => $request->user()?->id,
            ]);

            $year = $request->input('academic_year_id');
            $section = $request->input('section_id');
            $quarter = $request->input('quarter_id');

            // Validate required parameters
            if (!$year || !$section || !$quarter) {
                Log::warning('Certificate index: Missing required parameters', [
                    'year' => $year,
                    'section' => $section,
                    'quarter' => $quarter,
                ]);
                return response()->json([
                    'error' => 'Missing required parameters: academic_year_id, section_id, and quarter_id are required'
                ], 400);
            }

            Log::info('Fetching perfect attendance data');
            $perfectAttendance = $this->getPerfectAttendance($year, $section, $quarter);
            
            Log::info('Fetching honor roll data');
            $honorRoll = $this->getHonorRoll($year, $section, $quarter);

            // DEMO MODE: Always return quarter as complete
            Log::info('DEMO MODE: Quarter marked as complete');
            $quarterComplete = true;

            Log::info('Certificate index successful', [
                'perfect_attendance_count' => count($perfectAttendance),
                'honor_roll_count' => count($honorRoll),
                'quarter_complete' => $quarterComplete,
            ]);

            return response()->json([
                'perfect_attendance' => $perfectAttendance,
                'honor_roll' => $honorRoll,
                'quarter_complete' => $quarterComplete,
            ]);
        } catch (\Exception $e) {
            Log::error('Certificate index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            
            return response()->json([
                'error' => 'An error occurred while fetching certificate data',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'debug_info' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    public function preview(Request $request, $type, $studentId, $quarterId = null)
    {
        $student = Student::with('section.yearLevel', 'section.academicYear')->findOrFail($studentId);

        // Get academic year name
        $academicYear = $student->section->academicYear->name ?? 'N/A';

        if ($type === 'perfect_attendance') {
            // DEMO MODE: Skip completeness check
            // if (!$this->isAttendanceComplete($studentId, $quarterId)) {
            //     abort(403, 'Cannot preview certificate. Attendance data is incomplete for this quarter.');
            // }

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
            // DEMO MODE: Skip completeness check
            // if (!$this->isGradesComplete($studentId, $quarterId)) {
            //     abort(403, 'Cannot preview certificate. Grades data is incomplete for this student for the selected quarter.');
            // }

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

    public function download(Request $request, $type, $studentId, $quarterId = null)
    {
        $student = Student::with('section.yearLevel', 'section.academicYear')->findOrFail($studentId);

        // Get academic year name
        $academicYear = $student->section->academicYear->name ?? 'N/A';

        if ($type === 'perfect_attendance') {
            // DEMO MODE: Skip completeness check
            // if (!$this->isAttendanceComplete($studentId, $quarterId)) {
            //     abort(403, 'Cannot download certificate. Attendance data is incomplete for this quarter.');
            // }

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
            // DEMO MODE: Skip completeness check
            // if (!$this->isGradesComplete($studentId, $quarterId)) {
            //     abort(403, 'Cannot download certificate. Grades data is incomplete for this quarter.');
            // }

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

    public function downloadAll(Request $request)
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
                    // DEMO MODE: Skip completeness check
                    // if (!$this->isAttendanceComplete($student->id, $quarter)) {
                    //     continue;
                    // }

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
                    // DEMO MODE: Skip completeness check
                    // if (!$this->isGradesComplete($student->id, $quarter)) {
                    //     continue;
                    // }

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
                ->where('section_id', $sectionId)
                ->where('enrollment_status', 'enrolled');
        })->with([
            'attendances' => function ($query) use ($yearId, $quarterId) {
                $query->where('academic_year_id', $yearId);
                if ($quarterId) {
                    $query->where('quarter_id', $quarterId);
                }
            }
        ])->get();

        $perfect = [];

        foreach ($students as $student) {
            $attendances = $student->attendances;

            if ($attendances->count() === 0) continue;

            // Check if all are present (including excused and late as acceptable)
            $isPerfect = $attendances->every(function ($record) {
                return in_array($record->status, ['present', 'excused', 'late']);
            });

            if ($isPerfect) {
                $quarters = $attendances->pluck('quarter_id')->unique();
                
                // DEMO MODE: Always mark as can_generate = true
                $perfect[] = [
                    'id' => $student->id,
                    'student_name' => $student->fullName(),
                    'quarters' => $quarters->map(fn($id) => Quarter::find($id)?->name ?? 'Unknown')->join(', '),
                    'can_generate' => true,
                ];
            }
        }

        return $perfect;
    }

    private function getHonorRoll($yearId, $sectionId, $quarterId)
    {
        $students = Student::whereHas('enrollments', function ($query) use ($yearId, $sectionId) {
            $query->where('academic_year_id', $yearId)
                ->where('section_id', $sectionId)
                ->where('enrollment_status', 'enrolled');
        })->with([
            'grades' => function ($query) use ($yearId, $quarterId) {
                $query->where('academic_year_id', $yearId);
                if ($quarterId) {
                    $query->where('quarter_id', $quarterId);
                }
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
                // DEMO MODE: Always mark as can_generate = true
                $honors[] = [
                    'id' => $student->id,
                    'student_name' => $student->fullName(),
                    'grade_average' => round($average, 2),
                    'honor_type' => $honor,
                    'quarter' => $quarterName,
                    'can_generate' => true,
                ];
            }
        }

        return $honors;
    }

    /**
     * DEMO MODE: Always returns true
     * Check if a quarter is complete (end date has passed)
     */
    private function isQuarterComplete($quarterId)
    {
        // DEMO MODE: Always return true for demonstration
        return true;
        
        // Original code (commented out):
        // if (!$quarterId) {
        //     return false;
        // }
        // $quarter = Quarter::find($quarterId);
        // if (!$quarter) {
        //     return false;
        // }
        // return Carbon::now()->isAfter($quarter->end_date);
    }

    /**
     * DEMO MODE: Always returns true
     * Check if attendance data is complete for a student in a quarter
     */
    private function isAttendanceComplete($studentId, $quarterId)
    {
        // DEMO MODE: Always return true for demonstration
        return true;
        
        // Original code (commented out):
        // if (!$quarterId) {
        //     return false;
        // }
        // $quarter = Quarter::find($quarterId);
        // if (!$quarter) {
        //     return false;
        // }
        // $attendanceCount = Attendance::where('student_id', $studentId)
        //     ->where('quarter_id', $quarterId)
        //     ->count();
        // return $attendanceCount > 0;
    }

    /**
     * DEMO MODE: Always returns true
     * Check if grades data is complete for a student in a quarter
     */
    private function isGradesComplete($studentId, $quarterId)
    {
        // DEMO MODE: Always return true for demonstration
        return true;
        
        // Original code (commented out):
        // if (!$quarterId) {
        //     return false;
        // }
        // $quarter = Quarter::find($quarterId);
        // if (!$quarter) {
        //     return false;
        // }
        // $enrollment = \App\Models\Enrollment::where('student_id', $studentId)
        //     ->where('academic_year_id', $quarter->academic_year_id)
        //     ->first();
        // if (!$enrollment) {
        //     return false;
        // }
        // $expectedSubjectsCount = \App\Models\Subject::whereHas('yearLevelSubjects', function ($query) use ($enrollment) {
        //     $query->where('year_level_id', $enrollment->grade_level);
        // })->where('is_active', true)->count();
        // if ($expectedSubjectsCount <= 0) {
        //     $inferred = \App\Models\Grade::where('student_id', $studentId)
        //         ->when($quarterId, fn($q) => $q->where('quarter_id', $quarterId))
        //         ->distinct('subject_id')
        //         ->count('subject_id');
        //     if ($inferred > 0) {
        //         $expectedSubjectsCount = $inferred;
        //     } else {
        //         return false;
        //     }
        // }
        // $actualGradesCount = Grade::where('student_id', $studentId)
        //     ->where('quarter_id', $quarterId)
        //     ->count();
        // if ($expectedSubjectsCount <= 0) {
        //     return false;
        // }
        // return $actualGradesCount >= $expectedSubjectsCount;
    }
}