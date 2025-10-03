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
        $student = Auth::user();
        $academicYear = $this->getCurrentAcademicYear();

        if ($academicYear instanceof \Illuminate\Http\JsonResponse) {
            return $academicYear;
        }

        $quarters = Quarter::where('academic_year_id', $academicYear->id)->get();

        $honorRoll = collect();
        $perfectAttendance = collect();

        foreach ($quarters as $quarter) {
            $grades = Grade::where('student_id', $student->id)
                ->where('quarter_id', $quarter->id)
                ->get();

            if ($grades->count()) {
                $average = $grades->avg('grade');

                if ($average >= 90) {
                    $honorType = match (true) {
                        $average >= 98 => 'With Highest Honors',
                        $average >= 95 => 'With High Honors',
                        default        => 'With Honors',
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

            $attendances = Attendance::where('student_id', $student->id)
                ->whereBetween('attendance_date', [$quarter->start_date, $quarter->end_date])
                ->get();

            $totalDays = $attendances->count();
            $presentDays = $attendances->where('status', 'present')->count();

            if ($totalDays > 0 && $totalDays == $presentDays) {
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
            'certificate_count' => $honorRoll->count() + $perfectAttendance->count(),
            'honor_roll_count' => $honorCounts,
            'attendance_awards_count' => $perfectAttendance->count(),
            'academic_awards' => $honorRoll->values(),
            'attendance_awards' => $perfectAttendance->values(),
        ]);
    }

    public function downloadCertificate(Request $request)
    {
        $student = Auth::user();
        $currentYear = $this->getCurrentAcademicYear();

        if ($currentYear instanceof \Illuminate\Http\JsonResponse) {
            return $currentYear;
        }

        $type = $request->input('type');
        $quarterId = $request->input('quarter_id');

        $quarter = Quarter::findOrFail($quarterId);
        $studentData = Student::with(['user'])->findOrFail($student->id);

        if ($type === 'honor_roll') {
            $grades = Grade::where('student_id', $student->id)
                ->where('quarter_id', $quarterId)
                ->get();

            $average = $grades->avg('grade');

            if ($average < 90) {
                return response()->json(['message' => 'Not qualified for honor roll'], 403);
            }

            $honorType = match (true) {
                $average >= 98 => 'With Highest Honors',
                $average >= 95 => 'With High Honors',
                default        => 'With Honors',
            };

            $data = [
                'honor_type' => $honorType,
                'grade_average' => $average,
                'quarter' => $quarter->name,
                'academic_year' => $currentYear->name,
            ];

            $pdf = Pdf::loadView('certificates.honor_roll', [
                'student' => $studentData,
                'data' => $data
            ]);
        } elseif ($type === 'perfect_attendance') {
            $attendances = Attendance::where('student_id', $student->id)
                ->whereBetween('attendance_date', [$quarter->start_date, $quarter->end_date])
                ->get();

            $total = $attendances->count();
            $present = $attendances->where('status', 'present')->count();

            if ($total === 0 || $total !== $present) {
                return response()->json(['message' => 'Not qualified for perfect attendance'], 403);
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
            return response()->json(['message' => 'Invalid certificate type'], 422);
        }

        return $pdf->download("certificate-{$type}-{$quarter->name}.pdf");
    }

    private function getCurrentAcademicYear()
    {
        $year = AcademicYear::where('is_current', 1)->first();
        if (!$year) {
            return response()->json(['message' => 'Active academic year not found.'], 404);
        }
        return $year;
    }
}
