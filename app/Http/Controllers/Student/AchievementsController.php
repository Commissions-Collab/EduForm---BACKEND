<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Quarter;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AchievementsController extends Controller
{
    public function getCertificates(Request $request)
    {
        try {
            $user = Auth::user();
            $student = $user->student;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            $academicYear = $this->getCurrentAcademicYear();

            if (!$academicYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active academic year found'
                ], 404);
            }

            $quarters = Quarter::where('academic_year_id', $academicYear->id)
                ->orderBy('start_date')
                ->get();

            if ($quarters->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'certificate_count' => 0,
                        'honor_roll_count' => [
                            'with_honors' => 0,
                            'with_high_honors' => 0,
                            'with_highest_honors' => 0,
                        ],
                        'attendance_awards_count' => 0,
                        'academic_awards' => [],
                        'attendance_awards' => [],
                    ]
                ]);
            }

            $honorRoll = collect();
            $perfectAttendance = collect();

            foreach ($quarters as $quarter) {
                // Check honor roll eligibility
                $grades = Grade::where('student_id', $student->id)
                    ->where('quarter_id', $quarter->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->get();

                if ($grades->count() > 0) {
                    $average = $grades->avg('grade');

                    if ($average >= 90) {
                        $honorType = match (true) {
                            $average >= 98 => 'With Highest Honors',
                            $average >= 95 => 'With High Honors',
                            default => 'With Honors',
                        };

                        $honorRoll->push([
                            'type' => 'honor_roll',
                            'honor_type' => $honorType,
                            'issued_date' => Carbon::parse($quarter->end_date)->format('F d, Y'),
                            'description' => "{$honorType} for the {$quarter->name}",
                            'quarter_id' => $quarter->id,
                            'quarter' => $quarter->name,
                            'category' => 'Academic',
                            'average' => round($average, 2),
                        ]);
                    }
                }

                // Check perfect attendance eligibility
                $attendances = Attendance::where('student_id', $student->id)
                    ->whereBetween('attendance_date', [$quarter->start_date, $quarter->end_date])
                    ->where('academic_year_id', $academicYear->id)
                    ->get();

                $totalDays = $attendances->count();
                $presentDays = $attendances->where('status', 'present')->count();

                if ($totalDays > 0 && $totalDays === $presentDays) {
                    $perfectAttendance->push([
                        'type' => 'perfect_attendance',
                        'issued_date' => Carbon::parse($quarter->end_date)->format('F d, Y'),
                        'description' => "For 100% attendance during the {$quarter->name}",
                        'quarter_id' => $quarter->id,
                        'quarter' => $quarter->name,
                        'category' => 'Attendance',
                    ]);
                }
            }

            $honorCounts = [
                'with_honors' => $honorRoll->where('honor_type', 'With Honors')->count(),
                'with_high_honors' => $honorRoll->where('honor_type', 'With High Honors')->count(),
                'with_highest_honors' => $honorRoll->where('honor_type', 'With Highest Honors')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'certificate_count' => $honorRoll->count() + $perfectAttendance->count(),
                    'honor_roll_count' => $honorCounts,
                    'attendance_awards_count' => $perfectAttendance->count(),
                    'academic_awards' => $honorRoll->values(),
                    'attendance_awards' => $perfectAttendance->values(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch certificates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadCertificate(Request $request)
    {
        try {
            $user = Auth::user();
            $student = $user->student;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            $type = $request->input('type');
            $quarterId = $request->input('quarter_id');

            if (!$type || !$quarterId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Type and quarter_id are required'
                ], 400);
            }

            $currentYear = $this->getCurrentAcademicYear();

            if (!$currentYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active academic year found'
                ], 404);
            }

            $quarter = Quarter::where('id', $quarterId)
                ->where('academic_year_id', $currentYear->id)
                ->first();

            if (!$quarter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quarter not found'
                ], 404);
            }

            $studentData = Student::with(['user'])->findOrFail($student->id);

            if ($type === 'honor_roll') {
                // Verify student qualifies for honor roll
                $grades = Grade::where('student_id', $student->id)
                    ->where('quarter_id', $quarterId)
                    ->where('academic_year_id', $currentYear->id)
                    ->get();

                if ($grades->count() === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No grades found for this student in the selected quarter'
                    ], 404);
                }

                $average = $grades->avg('grade');

                if ($average < 90) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not qualified for honor roll'
                    ], 403);
                }

                $honorType = match (true) {
                    $average >= 98 => 'With Highest Honors',
                    $average >= 95 => 'With High Honors',
                    default => 'With Honors',
                };

                $data = [
                    'honor_type' => $honorType,
                    'grade_average' => round($average, 2),
                    'quarter' => $quarter->name,
                    'academic_year' => $currentYear->name,
                ];

                $pdf = Pdf::loadView('certificates.honor_roll', [
                    'student' => $studentData,
                    'data' => $data
                ]);
            } elseif ($type === 'perfect_attendance') {
                // Verify student has perfect attendance
                $attendances = Attendance::where('student_id', $student->id)
                    ->whereBetween('attendance_date', [$quarter->start_date, $quarter->end_date])
                    ->where('academic_year_id', $currentYear->id)
                    ->get();

                $totalDays = $attendances->count();
                $presentDays = $attendances->where('status', 'present')->count();

                if ($totalDays === 0 || $totalDays !== $presentDays) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not qualified for perfect attendance'
                    ], 403);
                }

                $data = [
                    'quarters' => $quarter->name,
                    'academic_year' => $currentYear->name,
                ];

                $pdf = Pdf::loadView('certificates.perfect_attendance', [
                    'student' => $studentData,
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid certificate type'
                ], 422);
            }

            $pdf->setPaper('A4', 'landscape');
            return $pdf->download("certificate-{$type}-{$quarter->name}.pdf");
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getCurrentAcademicYear()
    {
        try {
            // Try with boolean first
            $academicYear = AcademicYear::where('is_current', true)->first();

            // If not found, try with integer 1
            if (!$academicYear) {
                $academicYear = AcademicYear::where('is_current', 1)->first();
            }

            // If still not found, get the most recent one
            if (!$academicYear) {
                $academicYear = AcademicYear::orderBy('id', 'desc')->first();
            }

            return $academicYear;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
