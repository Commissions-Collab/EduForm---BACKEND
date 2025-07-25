<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\TeacherSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcademicRecordsController extends Controller
{
    public function getFilterOptions(Request $request)
    {
        $user = Auth::user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json(['error' => 'Teacher profile not found'], 404);
        }

        $academicYears = $this->getAcademicYearsWithQuarters($teacher->id);

        $filterOptions = [
            'academic_years' => $this->formatAcademicYears($academicYears),
            'assignments_by_year' => $this->getAssignmentsGroupedByYear($teacher->id, $academicYears)
        ];

        return response()->json($filterOptions);
    }

    // 👇 Helper: Fetch academic years and quarters
    private function getAcademicYearsWithQuarters($teacherId)
    {
        return AcademicYear::whereHas('teacherSubjects', function ($query) use ($teacherId) {
            $query->where('teacher_id', $teacherId);
        })->with('quarters')->get();
    }

    // 👇 Helper: Format academic year with quarters
    private function formatAcademicYears($academicYears)
    {
        return $academicYears->map(function ($year) {
            return [
                'id' => $year->id,
                'name' => $year->name,
                'is_current' => $year->is_current,
                'quarters' => $year->quarters->map(function ($quarter) {
                    return [
                        'id' => $quarter->id,
                        'name' => $quarter->name,
                        'start_date' => $quarter->start_date,
                        'end_date' => $quarter->end_date
                    ];
                })
            ];
        });
    }

    // 👇 Helper: Get subject + section assignments by year
    private function getAssignmentsGroupedByYear($teacherId, $academicYears)
    {
        $assignments = [];

        foreach ($academicYears as $year) {
            $teacherSubjects = TeacherSubject::where('teacher_id', $teacherId)
                ->where('academic_year_id', $year->id)
                ->with('subject')
                ->get();

            $sections = Section::where('academic_year_id', $year->id)
                ->with(['yearLevel', 'students'])
                ->get()
                ->groupBy('yearLevel.name');

            $yearData = [
                'academic_year_id' => $year->id,
                'academic_year_name' => $year->name,
                'subjects' => $teacherSubjects->map(function ($ts) {
                    return [
                        'id' => $ts->subject->id,
                        'name' => $ts->subject->name,
                        'teacher_subject_id' => $ts->id
                    ];
                })->values(),
                'year_levels' => []
            ];

            foreach ($sections as $yearLevelName => $sectionsInLevel) {
                $yearLevelData = [
                    'year_level_name' => $yearLevelName,
                    'year_level_id' => $sectionsInLevel->first()->year_level_id,
                    'sections' => $sectionsInLevel->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'name' => $section->name,
                            'student_count' => $section->students->where('enrollment_status', 'enrolled')->count()
                        ];
                    })->values()
                ];

                $yearData['year_levels'][] = $yearLevelData;
            }

            $assignments[] = $yearData;
        }

        return $assignments;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $teacher = Auth::user()->teacher;

            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found',
                ], 404);
            }

            $academicYear = $this->getCurrentAcademicYear($request->get('academic_year_id'));

            if (!$academicYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic year not found',
                ], 404);
            }

            $subjects = $teacher->subjects()
                ->with(['schedules.section'])
                ->where('academic_year_id', $academicYear->id)
                ->get();

            $defaultSection = $subjects->first();
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function getCurrentAcademicYear($academicYearId = null)
    {
        if ($academicYearId) {
            return AcademicYear::findOrFail($academicYearId);
        }

        return AcademicYear::where('is_current', true)->firstOrFail();
    }
}
