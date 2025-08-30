<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcademicRecordsController extends Controller
{
    public function getFilterOptions(Request $request)
    {
        $user = Auth::user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json(['error' => 'Teacher profile not found'], 404);
        }

        // Optional param to narrow sections by academic year
        $academicYearId = $request->integer('academic_year_id');

        // Whatever you already have for years+quarters
        $academicYears = $this->getAcademicYearsWithQuarters($teacher->id);
        $formattedYears = $this->formatAcademicYears($academicYears); // [{id,name,is_current,quarters:[...]}, ...]

        // Sections where this teacher has schedules (optionally filtered by academic year)
        $sectionIds = Schedule::query()
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->where('teacher_id', $teacher->id)
            ->pluck('section_id')
            ->unique()
            ->values();

        $sections = Section::query()
            ->whereIn('id', $sectionIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'academic_years' => $formattedYears,
            'sections' => $sections,
            // keep your existing payload for other screens
            'assignments_by_year' => $this->getAssignmentsGroupedByYear($teacher->id, $academicYears),
        ]);
    }


    public function getStudentsGrade(Request $request)
    {
        $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'quarter_id' => ['required', 'exists:quarters,id'],
            'section_id' => ['required', 'exists:sections,id'],
        ]);

        $user = Auth::user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        $allSubjects = Subject::whereHas('teacherSubjects', function ($query) use ($request, $teacher) {
            $query->where('academic_year_id', $request->academic_year_id);
            $query->where('teacher_id', $teacher->id);
        })->get();

        $teacherSubjects = TeacherSubject::where('teacher_id', $teacher->id)
            ->where('academic_year_id', $request->academic_year_id)
            ->with('subject')
            ->get()
            ->pluck('subject.id')
            ->toArray();

        $students = Student::whereHas('enrollments', function ($query) use ($request) {
            $query->where('section_id', $request->section_id)
                ->where('academic_year_id', $request->academic_year_id)
                ->where('enrollment_status', 'enrolled');
        })->with(['grades' => function ($query) use ($request) {
            $query->where('quarter_id', $request->quarter_id);
        }])->orderBy('last_name')->orderBy('first_name')->get();

        $studentsData = $students->map(function ($student) use ($allSubjects, $teacherSubjects) {
            $studentGrades = [];
            $totalGrade = 0;
            $subjectCount = 0;
            $allSubjectFilled = true;

            foreach ($allSubjects as $subject) {
                $grade = $student->grades->where('subject_id', $subject->id)->first();
                $gradeValue = $grade ? $grade->grade : null;

                $studentGrades[] = [
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                    'grade' => $gradeValue,
                    'can_edit' => in_array($subject->id, $teacherSubjects),
                    'grade_id' => $grade ? $grade->id : null
                ];

                if ($grade !== null) {
                    $totalGrade += $gradeValue;
                    $subjectCount++;
                } else {
                    $allSubjectFilled = false;
                }
            }

            $average = $allSubjectFilled && $subjectCount > 0 ? round($totalGrade / $subjectCount, 2) : null;

            return [
                'id' => $student->id,
                'name' => $student->fullName(),
                'student_number' => $student->student_number,
                'grades' => $studentGrades,
                'all_subjects_filled' => $allSubjectFilled,
                'status' => $this->getPassingStatus($average)
            ];
        });

        return response()->json([
            'students' => $studentsData,
            'subjects' => $allSubjects->map(function ($subject) use ($teacherSubjects) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'can_edit' => in_array($subject->id, $teacherSubjects)
                ];
            })
        ]);
    }

    public function updateGrade(Request $request)
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'quarter_id' => ['required', 'exists:quarters,id'],
            'grade' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $user = Auth::user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        $hasPermission = TeacherSubject::where('teacher_id', $teacher->id)
            ->where('subject_id', $request->subject_id)
            ->exists();

        if (!$hasPermission) {
            return response()->json(['error' => 'You do not have permission to edit grades for this subject'], 403);
        }

        try {
            DB::beginTransaction();

            $grade = Grade::updateOrCreate(
                [
                    'student_id' => $request->student_id,
                    'subject_id' => $request->subject_id,
                    'quarter_id' => $request->quarter_id
                ],
                [
                    'grade' => $request->grade,
                    'recorded_by' => $teacher->id,
                    'updated_at' => now()
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grade updated successfully',
                'grade' => $grade
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update grade' . $e], 500);
        }
    }

    public function getGradeStatistics(Request $request)
    {
        $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'quarter_id' => ['required', 'exists:quarters,id'],
            'section_id' => ['required', 'exists:sections,id'],
        ]);

        $students = Student::whereHas('enrollments', function ($query) use ($request) {
            $query->where('section_id', $request->section_id)
                ->where('academic_year_id', $request->academic_year_id)
                ->where('enrollment_status', 'enrolled');
        })->with(['grades' => function ($query) use ($request) {
            $query->where('quarter_id', $request->quarter_id);
        }])->orderBy('last_name')->orderBy('first_name')->get();

        $allSubjects = Subject::whereHas('teacherSubjects', function ($query) use ($request) {
            $query->where('academic_year_id', $request->academic_year_id);
        })->get();

        $statistics = [
            'total_students' => $students->count(),
            'students_with_complete_grades' => 0,
            'passing_students' => 0,
            'failing_students' => 0,
            'subject_averages' => []
        ];

        $subjectTotals = [];
        $subjectCount = [];

        foreach ($students as $student) {
            $studentGrades = [];
            $totalGrades = 0;
            $subjectCount = 0;
            $allSubjectsFilled = true;

            foreach ($allSubjects as $subject) {
                $grade = $student->grades->where('subject_id', $subject->id)->first();
                $gradeValue = $grade ? $grade->grade : null;

                if ($gradeValue !== null) {
                    $studentGrades[] = $gradeValue;
                    $totalGrades += $gradeValue;
                    $subjectCount++;

                    // Track subject totals for class averages
                    if (!isset($subjectTotals[$subject->id])) {
                        $subjectTotals[$subject->id] = 0;
                        $subjectCounts[$subject->id] = 0;
                    }
                    $subjectTotals[$subject->id] += $gradeValue;
                    $subjectCounts[$subject->id]++;
                } else {
                    $allSubjectsFilled = false;
                }
            }

            if ($allSubjectsFilled && $subjectCount > 0) {
                $statistics['students_with_complete_grades']++;
                $average = $totalGrades / $subjectCount;

                if ($average >= 75) {
                    $statistics['passing_students']++;
                } else {
                    $statistics['failing_students']++;
                }
            }
        }

        // Calculate subject averages
        foreach ($allSubjects as $subject) {
            if (isset($subjectCounts[$subject->id]) && $subjectCounts[$subject->id] > 0) {
                $statistics['subject_averages'][] = [
                    'subject_name' => $subject->name,
                    'average' => round($subjectTotals[$subject->id] / $subjectCounts[$subject->id], 2),
                    'student_count' => $subjectCounts[$subject->id]
                ];
            }
        }

        return response()->json($statistics);
    }

    private function getPassingStatus($average)
    {
        if ($average === null) {
            return 'Incomplete';
        }

        return $average >= 75 ? 'Passing' : 'Failing';
    }

    // Helper: Fetch academic years and quarters
    private function getAcademicYearsWithQuarters($teacherId)
    {
        return AcademicYear::whereHas('teacherSubjects', function ($query) use ($teacherId) {
            $query->where('teacher_id', $teacherId);
        })->with('quarters')->get();
    }
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

    // Helper: Get subject + section assignments by year
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
}
