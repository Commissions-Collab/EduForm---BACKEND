<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuperAdminFormController extends Controller
{
    /**
     * Get filter options for SF5/SF6 - SuperAdmin has access to all sections
     */
    public function getFilterOptions(Request $request)
    {
        try {
            $academicYears = AcademicYear::orderBy('is_current', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            $filterOptions = [
                'academic_years' => $academicYears->map(function ($year) {
                    return [
                        'id' => $year->id,
                        'name' => $year->name,
                        'is_current' => $year->is_current
                    ];
                }),
                'sections_by_year' => [],
                'default_section' => null,
                'has_accessible_sections' => false,
            ];

            foreach ($academicYears as $year) {
                $sections = Section::where('academic_year_id', $year->id)
                    ->with(['yearLevel'])
                    ->get()
                    ->groupBy('yearLevel.name');

                $yearSections = [];
                $yearHasAccessibleSections = false;

                foreach ($sections as $yearLevelName => $sectionsInLevel) {
                    $sectionsData = [];

                    foreach ($sectionsInLevel as $section) {
                        $completenessData = $this->checkSectionGradeCompleteness($section->id, $year->id);

                        $sectionsData[] = [
                            'id' => $section->id,
                            'name' => $section->name,
                            'student_count' => $section->students()->whereHas('enrollments', function ($q) use ($year) {
                                $q->where('enrollment_status', 'enrolled')
                                    ->where('academic_year_id', $year->id);
                            })->count(),
                            'is_accessible' => $completenessData['is_complete'],
                            'completion_percentage' => $completenessData['completion_percentage'],
                            'students_with_complete_grades' => $completenessData['students_with_complete_grades'],
                            'total_students' => $completenessData['total_students']
                        ];

                        if ($completenessData['is_complete']) {
                            $yearHasAccessibleSections = true;
                        }
                    }

                    if (!empty($sectionsData)) {
                        $yearSections[] = [
                            'year_level_name' => $yearLevelName,
                            'year_level_id' => $sectionsInLevel->first()->year_level_id,
                            'sections' => $sectionsData
                        ];
                    }
                }

                if ($yearHasAccessibleSections) {
                    $filterOptions['has_accessible_sections'] = true;
                }

                $filterOptions['sections_by_year'][] = [
                    'academic_year_id' => $year->id,
                    'academic_year_name' => $year->name,
                    'year_levels' => $yearSections,
                    'has_accessible_sections' => $yearHasAccessibleSections
                ];
            }

            return response()->json($filterOptions);
        } catch (\Exception $e) {
            Log::error('SuperAdminFormController filter options error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch filter options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SF5/SF6 statistics for a specific section
     */
    public function getFormStatistics(Request $request)
    {
        $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'form_type' => ['required', 'in:sf5,sf6'],
        ]);

        try {
            $academicYear = AcademicYear::findOrFail($request->academic_year_id);
            $section = Section::with(['yearLevel'])->findOrFail($request->section_id);
            $formType = $request->form_type;

            $quarters = Quarter::where('academic_year_id', $request->academic_year_id)
                ->orderBy('start_date')
                ->get();

            $subjects = Subject::whereHas('teacherSubjects', function ($query) use ($request) {
                $query->where('academic_year_id', $request->academic_year_id);
            })->get();

            $students = Student::whereHas('enrollments', function ($query) use ($request) {
                $query->where('section_id', $request->section_id)
                    ->where('academic_year_id', $request->academic_year_id)
                    ->where('enrollment_status', 'enrolled');
            })
                ->with([
                    'grades' => function ($query) use ($request) {
                        $query->whereHas('quarter', function ($q) use ($request) {
                            $q->where('academic_year_id', $request->academic_year_id);
                        });
                    },
                    'attendances' => function ($query) use ($request) {
                        $query->whereHas('academicYear', function ($q) use ($request) {
                            $q->where('id', $request->academic_year_id);
                        });
                    }
                ])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            $formData = [];
            $gradesComplete = true;

            $overallStatistics = [
                'total_students' => $students->count(),
                'passing_students' => 0,
                'failing_students' => 0,
                'with_honors' => 0,
                'high_honors' => 0,
                'highest_honors' => 0,
                'with_discrepancies' => 0,
                'incomplete_grades' => 0,
                'attendance_issues' => 0
            ];

            foreach ($students as $student) {
                $studentData = $this->calculateStudentPromotion($student, $subjects, $quarters);
                $formData[] = $studentData;

                if (!$studentData['grades_complete']) {
                    $gradesComplete = false;
                    $overallStatistics['incomplete_grades']++;
                }

                if ($studentData['promotion_status'] === 'Pass') {
                    $overallStatistics['passing_students']++;
                } else {
                    $overallStatistics['failing_students']++;
                }

                if ($studentData['has_discrepancy']) {
                    $overallStatistics['with_discrepancies']++;
                }

                if ($studentData['attendance_percentage'] < 75) {
                    $overallStatistics['attendance_issues']++;
                }

                if ($studentData['final_average'] >= 98) {
                    $overallStatistics['highest_honors']++;
                } elseif ($studentData['final_average'] >= 95) {
                    $overallStatistics['high_honors']++;
                } elseif ($studentData['final_average'] >= 90) {
                    $overallStatistics['with_honors']++;
                }
            }

            if (!$gradesComplete) {
                return response()->json([
                    'message' => 'The selected section is not ready: incomplete grades present.',
                    'accessible' => false
                ], 403);
            }

            return response()->json([
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'year_level' => $section->yearLevel->name,
                    'capacity' => $section->capacity
                ],
                'academic_year' => [
                    'id' => $academicYear->id,
                    'name' => $academicYear->name
                ],
                'form_type' => $formType,
                'students' => $formData,
                'overall_statistics' => $overallStatistics,
                'accessible' => true
            ]);
        } catch (\Exception $e) {
            Log::error('SuperAdminFormController statistics error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch form statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export form as PDF
     */
    public function exportFormPDF(Request $request)
    {
        $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'form_type' => ['required', 'in:sf5,sf6'],
        ]);

        try {
            // Get form data
            $request->merge([
                'academic_year_id' => (int)$request->academic_year_id,
                'section_id' => (int)$request->section_id,
            ]);

            $formResponse = $this->getFormStatistics($request);
            $formData = json_decode($formResponse->getContent(), true);

            if (!$formData['accessible']) {
                return response()->json(['message' => 'Cannot export: form not accessible'], 403);
            }

            // Here you would integrate with a PDF generation library like TCPDF or similar
            // For now, returning the data that would be exported
            return response()->json([
                'message' => 'PDF export prepared successfully',
                'data' => $formData,
                'file_name' => "SF{$request->form_type}_" . $formData['section']['name'] . ".pdf"
            ]);
        } catch (\Exception $e) {
            Log::error('SuperAdminFormController export error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export form',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview form for a section
     */
    public function previewForm(Request $request, $type, $sectionId)
    {
        try {
            if (!in_array($type, ['sf5', 'sf6'])) {
                return response()->json(['message' => 'Invalid form type'], 400);
            }

            $request->validate([
                'academic_year_id' => ['required', 'exists:academic_years,id'],
            ]);

            $request->merge([
                'section_id' => $sectionId,
                'form_type' => $type,
            ]);

            return $this->getFormStatistics($request);
        } catch (\Exception $e) {
            Log::error('SuperAdminFormController preview error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to preview form',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate individual student promotion data
     */
    private function calculateStudentPromotion($student, $subjects, $quarters)
    {
        $subjectAverages = [];
        $allGradesComplete = true;
        $hasFailingGrade = false;

        foreach ($subjects as $subject) {
            $subjectGrades = [];

            foreach ($quarters as $quarter) {
                $grade = $student->grades->where('subject_id', $subject->id)
                    ->where('quarter_id', $quarter->id)
                    ->first();
                if ($grade) {
                    $subjectGrades[] = $grade->grade;
                }
            }

            if (count($subjectGrades) > 0) {
                $average = array_sum($subjectGrades) / count($subjectGrades);
                $subjectAverages[$subject->id] = round($average, 2);

                if ($average < 75) {
                    $hasFailingGrade = true;
                }
            } else {
                $subjectAverages[$subject->id] = null;
                $allGradesComplete = false;
            }
        }

        $validSubjectAverages = array_filter($subjectAverages, function ($avg) {
            return $avg !== null;
        });

        $finalAverage = count($validSubjectAverages) > 0
            ? round(array_sum($validSubjectAverages) / count($validSubjectAverages), 2)
            : null;

        $totalDays = $student->attendances->count();
        $presentDays = $student->attendances->where('status', 'present')->count();
        $attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;

        $promotionStatus = 'Incomplete';
        $hasDiscrepancy = false;

        if ($allGradesComplete) {
            if ($finalAverage >= 75 && !$hasFailingGrade && $attendancePercentage >= 75) {
                $promotionStatus = 'Pass';
            } else {
                $promotionStatus = 'Fail';
                $hasDiscrepancy = true;
            }
        }

        $honorClassification = 'None';
        if ($finalAverage) {
            if ($finalAverage >= 95) {
                $honorClassification = 'Highest Honors';
            } elseif ($finalAverage >= 90) {
                $honorClassification = 'High Honors';
            } elseif ($finalAverage >= 85) {
                $honorClassification = 'With Honors';
            }
        }

        return [
            'student_id' => $student->id,
            'student_name' => $student->fullName(),
            'final_average' => $finalAverage,
            'attendance_percentage' => $attendancePercentage,
            'promotion_status' => $promotionStatus,
            'honor_classification' => $honorClassification,
            'has_discrepancy' => $hasDiscrepancy,
            'grades_complete' => $allGradesComplete,
            'has_failing_grade' => $hasFailingGrade,
            'total_days' => $totalDays,
            'present_days' => $presentDays
        ];
    }

    /**
     * Check if a section has complete grade data
     */
    private function checkSectionGradeCompleteness($sectionId, $academicYearId)
    {
        $quarters = Quarter::where('academic_year_id', $academicYearId)->get();

        $subjects = Subject::whereHas('teacherSubjects', function ($query) use ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        })->get();

        $students = Student::whereHas('enrollments', function ($query) use ($sectionId, $academicYearId) {
            $query->where('section_id', $sectionId)
                ->where('academic_year_id', $academicYearId)
                ->where('enrollment_status', 'enrolled');
        })->with(['grades' => function ($query) use ($academicYearId) {
            $query->whereHas('quarter', function ($q) use ($academicYearId) {
                $q->where('academic_year_id', $academicYearId);
            });
        }])->get();

        if ($students->isEmpty() || $subjects->isEmpty() || $quarters->isEmpty()) {
            return [
                'is_complete' => false,
                'completion_percentage' => 0,
                'students_with_complete_grades' => 0,
                'total_students' => $students->count()
            ];
        }

        $studentsWithCompleteGrades = 0;
        $totalExpectedGrades = $subjects->count() * $quarters->count();

        foreach ($students as $student) {
            $studentGradeCount = 0;

            foreach ($subjects as $subject) {
                foreach ($quarters as $quarter) {
                    $hasGrade = $student->grades->where('subject_id', $subject->id)
                        ->where('quarter_id', $quarter->id)
                        ->isNotEmpty();
                    if ($hasGrade) {
                        $studentGradeCount++;
                    }
                }
            }

            if ($studentGradeCount === $totalExpectedGrades) {
                $studentsWithCompleteGrades++;
            }
        }

        $completionPercentage = $students->count() > 0
            ? round(($studentsWithCompleteGrades / $students->count()) * 100, 1)
            : 0;

        return [
            'is_complete' => $studentsWithCompleteGrades === $students->count() && $students->count() > 0,
            'completion_percentage' => $completionPercentage,
            'students_with_complete_grades' => $studentsWithCompleteGrades,
            'total_students' => $students->count()
        ];
    }
}
