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

class GradeController extends Controller
{
    public function getStudentGrade()
    {
        try {
            $student = Auth::user()->student;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            $currentYear = $this->getCurrentAcademicYear();
            $today = Carbon::today();

            // Get current quarter
            $quarter = Quarter::where('academic_year_id', $currentYear->id)
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            // If no active quarter, get the first quarter of the year
            if (!$quarter) {
                $quarter = Quarter::where('academic_year_id', $currentYear->id)
                    ->orderBy('start_date')
                    ->first();
            }

            if (!$quarter) {
                return response()->json([
                    'success' => false,
                    'message' => 'No quarters found for this academic year'
                ], 404);
            }

            // Get grades for current student in current quarter
            $grades = Grade::with(['subject', 'recordedBy.teacher'])
                ->where('student_id', $student->id)
                ->where('academic_year_id', $currentYear->id)
                ->where('quarter_id', $quarter->id)
                ->get();

            // Get previous quarter for comparison
            $previousQuarter = Quarter::where('academic_year_id', $currentYear->id)
                ->where('id', '<', $quarter->id)
                ->orderByDesc('id')
                ->first();

            $previousGrades = $previousQuarter
                ? Grade::where('student_id', $student->id)
                ->where('quarter_id', $previousQuarter->id)
                ->get()
                ->keyBy('subject_id')
                : collect();

            // Get class averages for comparison
            $classAverages = Grade::select('subject_id', DB::raw('AVG(grade) as average'))
                ->where('quarter_id', $quarter->id)
                ->where('academic_year_id', $currentYear->id)
                ->groupBy('subject_id')
                ->pluck('average', 'subject_id');

            // Format subject grades with trends
            $subjectGrades = $grades->map(function ($grade) use ($classAverages, $previousGrades) {
                $subjectId = $grade->subject_id;
                $previous = $previousGrades->get($subjectId);
                $trend = 0;

                if ($previous && is_numeric($previous->grade) && $previous->grade > 0) {
                    $trend = round((($grade->grade - $previous->grade) / $previous->grade) * 100);
                }

                return [
                    'subject_id' => $grade->subject_id,
                    'subject' => $grade->subject?->name ?? 'Unknown Subject',
                    'grade' => round($grade->grade),
                    'class_average' => isset($classAverages[$subjectId]) ? round($classAverages[$subjectId]) : null,
                    'trend' => $trend,
                    'teacher' => $grade->recordedBy?->teacher?->fullName() ?? 'N/A',
                ];
            });

            $quarterAverage = $subjectGrades->count() > 0
                ? round($subjectGrades->avg('grade'), 1)
                : 0;

            // Determine honors eligibility
            $honors = null;
            if ($quarterAverage >= 90 && $quarterAverage < 95) {
                $honors = 'With Honors';
            } elseif ($quarterAverage >= 95 && $quarterAverage < 98) {
                $honors = 'With High Honors';
            } elseif ($quarterAverage >= 98) {
                $honors = 'With Highest Honors';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'quarter' => $quarter->name,
                    'quarter_id' => $quarter->id,
                    'quarter_average' => $quarterAverage,
                    'honors_eligibility' => $honors,
                    'grades' => $subjectGrades->values(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student grades',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function quarterFilter()
    {
        try {
            $student = Auth::user()->student;

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            $currentYear = $this->getCurrentAcademicYear();

            $quarters = Quarter::where('academic_year_id', $currentYear->id)
                ->select('id', 'name')
                ->orderBy('start_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'quarters' => $quarters
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch quarters',
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

            if (!$academicYear) {
                throw new \Exception('No academic year found in database');
            }

            return $academicYear;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
