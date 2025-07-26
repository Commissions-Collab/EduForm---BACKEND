<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PromotionReportController extends Controller
{
    public function getPromotionReportStatistics(Request $request)
    {
        $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'section_id' => ['required', 'exists:sections,id'],
        ]);

        $user = Auth::user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json(['error' => 'Teacher profile not found'], 404);
        }

        $quarters = Quarter::where('academic_year_id', $request->academic_year_id)
            ->orderBy('start_date')
            ->get();

        // $fourthQuarter = $quarters->where('name', '4th Quarter')->first();

        // // Still block early access if 4th quarter isn’t done
        // if ($fourthQuarter && Carbon::parse($fourthQuarter->end_date)->isFuture()) {
        //     return response()->json([
        //         'message' => 'The 4th quarter is not yet finished.',
        //         'accessible' => false
        //     ], 403);
        // }

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

        $promotionData = [];
        $gradesComplete = true;
        $hasFailingGrade = false;

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
            $promotionData[] = $studentData;

            if (!$studentData['grades_complete']) {
                $gradesComplete = false;
                $overallStatistics['incomplete_grades']++;
            }

            if ($studentData['has_failing_grade']) {
                $hasFailingGrade = true;
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

            // Honor roll classification
            if ($studentData['final_average'] >= 98) {
                $overallStatistics['highest_honors']++;
            } elseif ($studentData['final_average'] >= 95) {
                $overallStatistics['high_honors']++;
            } elseif ($studentData['final_average'] >= 90) {
                $overallStatistics['with_honors']++;
            }
        }

        // Now check if section is ready based on promotion logic
        if (!$gradesComplete) {
            return response()->json([
                'message' => 'The selected section is not ready: incomplete grades or failing grades present.',
                'accessible' => false
            ], 403);
        }

        return response()->json([
            'students' => $promotionData,
            'overall_statistics' => $overallStatistics,
            'accessible' => true
        ]);
    }



    /**
     * Calculate individual student promotion data
     */
    private function calculateStudentPromotion($student, $subjects, $quarters)
    {
        $subjectAverages = [];
        $allGradesComplete = true;
        $hasFailingGrade = false;

        // Calculate subject averages (across all quarters)
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

        // Calculate final average
        $validSubjectAverages = array_filter($subjectAverages, function ($avg) {
            return $avg !== null;
        });

        $finalAverage = count($validSubjectAverages) > 0
            ? round(array_sum($validSubjectAverages) / count($validSubjectAverages), 2)
            : null;

        // Calculate attendance
        $totalDays = $student->attendances->count();
        $presentDays = $student->attendances->where('status', 'present')->count();
        $attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;

        // Determine promotion status
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

        // Determine honor classification
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
     * Get filter options for promotion reports with grade completeness check
     */
    public function getPromotionFilterOptions(Request $request)
    {
        $user = Auth::user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json(['error' => 'Teacher profile not found'], 404);
        }

        $academicYears = AcademicYear::whereHas('teacherSubjects', function ($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })->get();

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
            'message' => null
        ];

        $hasAnyAccessibleSections = false;
        $defaultSection = null;

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

                    // Check if this section is accessible and can be default
                    if ($completenessData['is_complete']) {
                        $yearHasAccessibleSections = true;
                        $hasAnyAccessibleSections = true;

                        // Set as default if it's from current year and no default is set yet
                        if ($year->is_current && !$defaultSection) {
                            $defaultSection = $section->id;
                        }
                    }
                }

                $yearSections[] = [
                    'year_level_name' => $yearLevelName,
                    'year_level_id' => $sectionsInLevel->first()->year_level_id,
                    'sections' => $sectionsData
                ];
            }

            $filterOptions['sections_by_year'][] = [
                'academic_year_id' => $year->id,
                'academic_year_name' => $year->name,
                'year_levels' => $yearSections,
                'has_accessible_sections' => $yearHasAccessibleSections
            ];
        }

        // Set overall accessibility and messages
        $filterOptions['has_accessible_sections'] = $hasAnyAccessibleSections;
        $filterOptions['default_section'] = $defaultSection;

        if (!$hasAnyAccessibleSections) {
            $filterOptions['message'] = [
                'type' => 'warning',
                'title' => 'No Sections Available',
                'content' => 'All sections have incomplete grade data. Promotion reports can only be generated for sections with complete grades across all subjects and quarters. Please ensure all grades are entered before accessing this page.'
            ];
        } else {
            // Count accessible vs total sections
            $totalSections = 0;
            $accessibleSections = 0;

            foreach ($filterOptions['sections_by_year'] as $yearData) {
                foreach ($yearData['year_levels'] as $levelData) {
                    foreach ($levelData['sections'] as $sectionData) {
                        $totalSections++;
                        if ($sectionData['is_accessible']) {
                            $accessibleSections++;
                        }
                    }
                }
            }

            if ($accessibleSections < $totalSections) {
                $filterOptions['message'] = [
                    'type' => 'info',
                    'title' => 'Partial Data Available',
                    'content' => "Only {$accessibleSections} of {$totalSections} sections have complete grade data. Sections with incomplete grades are disabled and marked accordingly."
                ];
            }
        }

        return response()->json($filterOptions);
    }

    /**
     * Check if a section has complete grade data for promotion reports
     */
    private function checkSectionGradeCompleteness($sectionId, $academicYearId)
    {
        // Get all quarters for the academic year
        $quarters = Quarter::where('academic_year_id', $academicYearId)->get();

        // Get all subjects for this academic year
        $subjects = Subject::whereHas('teacherSubjects', function ($query) use ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        })->get();

        // Get students in the section
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

            // Student has complete grades if they have grades for all subject-quarter combinations
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
