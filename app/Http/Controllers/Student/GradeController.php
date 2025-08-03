<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Quarter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GradeController extends Controller
{
    public function getStudentGrade()
    {
        $student = Auth::user();
        $currentYear = $this->getCurrentAcademicYear();
        $today = Carbon::today();

        $quarter = Quarter::where('start_date', '<', $today)
            ->where('end_date', '>=', $today)
            ->first();

        if (!$quarter) {
            return response()->json(['error' => 'No active quarter found.'], 404);
        }

        $previousQuarter = Quarter::where('academic_year_id', $currentYear->id)
            ->where('id', '<', $quarter->id)
            ->orderByDesc('id')
            ->first();

        $grades = Grade::with(['subject', 'recordedBy.teacher'])
            ->where('student_id', $student->id)
            ->where('academic_year_id', $currentYear->id)
            ->where('quarter_id', $quarter->id)
            ->get();

        $previousGrades = $previousQuarter
            ? Grade::where('student_id', $student->id)
            ->where('quarter_id', $previousQuarter->id)
            ->get()
            ->keyBy('subject_id')
            : collect();

        $classAverages = Grade::select('subject_id', DB::raw('AVG(grade) as average'))
            ->where('quarter_id', $quarter->id)
            ->groupBy('subject_id')
            ->pluck('average', 'subject_id');

        $subjectGrades = $grades->map(function ($grade) use ($classAverages, $previousGrades) {
            $subjectId = $grade->subject_id;
            $previous = $previousGrades->get($subjectId);
            $trend = 0;

            if ($previous && is_numeric($previous->grade) && $previous->grade > 0) {
                $trend = round((($grade->grade - $previous->grade) / $previous->grade) * 100);
            }

            return [
                'subject' => $grade->subject->name,
                'grade' => round($grade->grade),
                'class_average' => isset($classAverages[$subjectId]) ? round($classAverages[$subjectId]) : null,
                'trend' => $trend,
                'teacher' => $grade->recordedBy->teacher->fullName() ?? 'N/A',
            ];
        });

        $quarterAverage = $subjectGrades->count() > 0
            ? round($subjectGrades->avg('grade'), 1)
            : 0;

        // Honors Eligibility
        $honors = null;
        if ($quarterAverage >= 90 && $quarterAverage < 95) {
            $honors = 'With Honors';
        } elseif ($quarterAverage >= 95 && $quarterAverage < 98) {
            $honors = 'With High Honors';
        } elseif ($quarterAverage >= 98) {
            $honors = 'With Highest Honors';
        }

        return response()->json([
            'quarter' => $quarter->name,
            'quarter_average' => $quarterAverage,
            'honors_eligibility' => $honors,
            'grades' => $subjectGrades,
        ]);
    }

    public function quarterFilter()
    {
        $currentYear = $this->getCurrentAcademicYear();

        $quarters = Quarter::where('academic_year_id', $currentYear->id)
            ->select('id', 'name')
            ->get();

        return response()->json([
            'quarters' => $quarters
        ]);
    }

    private function getCurrentAcademicYear()
    {
        $year = AcademicYear::where('is_current', 1)->first();
        if (!$year) {
            abort(404, 'Active academic year not found.');
        }
        return $year;
    }
}
