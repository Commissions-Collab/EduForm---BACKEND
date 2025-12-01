<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Quarter;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

    /**
     * Export SF5 (School Form 5) Report on Promotion & Level of Proficiency as Excel
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function exportSF5Excel(Request $request)
    {
        try {
            $request->validate([
                'section_id' => 'required|exists:sections,id',
                'academic_year_id' => 'required|exists:academic_years,id',
            ]);

            $teacher = Auth::user()->teacher;
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found'
                ], 404);
            }

            $sectionId = $request->get('section_id');
            $academicYearId = $request->get('academic_year_id');

            // Get section and academic year
            $section = Section::with(['yearLevel', 'academicYear'])->findOrFail($sectionId);
            $academicYear = AcademicYear::findOrFail($academicYearId);

            // Verify teacher has access to this section
            // Teacher can access if they are a section advisor OR have schedules for this section
            $isSectionAdvisor = DB::table('section_advisors')
                ->where('section_id', $sectionId)
                ->where('teacher_id', $teacher->id)
                ->where('academic_year_id', $academicYearId)
                ->exists();

            $hasSchedule = Schedule::where('section_id', $sectionId)
                ->where('teacher_id', $teacher->id)
                ->where('academic_year_id', $academicYearId)
                ->where('is_active', true)
                ->exists();

            if (!$isSectionAdvisor && !$hasSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this section'
                ], 403);
            }

            // Get quarters and subjects
            $quarters = Quarter::where('academic_year_id', $academicYearId)
                ->orderBy('start_date')
                ->get();

            $subjects = Subject::whereHas('teacherSubjects', function ($query) use ($academicYearId) {
                $query->where('academic_year_id', $academicYearId);
            })->get();

            // Get enrolled students
            $students = Student::whereHas('enrollments', function ($query) use ($sectionId, $academicYearId) {
                $query->where('section_id', $sectionId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('enrollment_status', 'enrolled');
            })
                ->with([
                    'grades' => function ($query) use ($academicYearId) {
                        $query->whereHas('quarter', function ($q) use ($academicYearId) {
                            $q->where('academic_year_id', $academicYearId);
                        });
                    },
                    'attendances' => function ($query) use ($academicYearId) {
                        $query->whereHas('academicYear', function ($q) use ($academicYearId) {
                            $q->where('id', $academicYearId);
                        });
                    }
                ])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            // Process student data
            $promotionData = [];
            $summaryStats = [
                'promoted' => ['male' => 0, 'female' => 0, 'total' => 0],
                'irregular' => ['male' => 0, 'female' => 0, 'total' => 0],
                'retained' => ['male' => 0, 'female' => 0, 'total' => 0],
                'beginning' => ['male' => 0, 'female' => 0, 'total' => 0],
                'developing' => ['male' => 0, 'female' => 0, 'total' => 0],
                'approaching' => ['male' => 0, 'female' => 0, 'total' => 0],
                'proficient' => ['male' => 0, 'female' => 0, 'total' => 0],
                'advanced' => ['male' => 0, 'female' => 0, 'total' => 0],
            ];

            foreach ($students as $student) {
                $studentData = $this->calculateStudentPromotion($student, $subjects, $quarters);
                
                $finalAverage = $studentData['final_average'] ?? 0;
                $promotionStatus = $studentData['promotion_status'] ?? 'Incomplete';
                
                // Determine action taken
                $actionTaken = 'RETAINED';
                if ($promotionStatus === 'Pass') {
                    $actionTaken = 'PROMOTED';
                } elseif ($promotionStatus === 'Incomplete') {
                    $actionTaken = 'IRREGULAR';
                }

                // Determine proficiency level
                $proficiencyLevel = 'BEGINNING';
                if ($finalAverage >= 90) {
                    $proficiencyLevel = 'ADVANCED';
                } elseif ($finalAverage >= 85) {
                    $proficiencyLevel = 'PROFICIENT';
                } elseif ($finalAverage >= 80) {
                    $proficiencyLevel = 'APPROACHING';
                } elseif ($finalAverage >= 75) {
                    $proficiencyLevel = 'DEVELOPING';
                }

                // Format general average (2 decimals for regular, 3 for honor)
                $generalAverage = $finalAverage > 0 ? number_format($finalAverage, 2) : '';
                $descriptiveGrade = $this->getDescriptiveGrade($finalAverage);

                $gender = strtolower($student->gender ?? 'male');
                $genderKey = $gender === 'female' ? 'female' : 'male';

                // Update summary stats
                if ($actionTaken === 'PROMOTED') {
                    $summaryStats['promoted'][$genderKey]++;
                    $summaryStats['promoted']['total']++;
                } elseif ($actionTaken === 'IRREGULAR') {
                    $summaryStats['irregular'][$genderKey]++;
                    $summaryStats['irregular']['total']++;
                } else {
                    $summaryStats['retained'][$genderKey]++;
                    $summaryStats['retained']['total']++;
                }

                // Update proficiency stats
                $proficiencyKey = strtolower($proficiencyLevel);
                if (isset($summaryStats[$proficiencyKey])) {
                    $summaryStats[$proficiencyKey][$genderKey]++;
                    $summaryStats[$proficiencyKey]['total']++;
                }

                $promotionData[] = [
                    'lrn' => $student->lrn ?? '',
                    'name' => trim($student->last_name . ', ' . $student->first_name . ' ' . ($student->middle_name ?? '')),
                    'general_average' => $generalAverage,
                    'descriptive_grade' => $descriptiveGrade,
                    'action_taken' => $actionTaken,
                    'incomplete_subjects_previous' => '', // Can be filled from student data
                    'incomplete_subjects_current' => '', // Can be filled from student data
                    'gender' => $genderKey,
                ];
            }

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SF5 Promotion Report');

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(12); // LRN
            $sheet->getColumnDimension('B')->setWidth(35); // Learner's Name
            $sheet->getColumnDimension('C')->setWidth(18); // General Average
            $sheet->getColumnDimension('D')->setWidth(15); // Action Taken
            $sheet->getColumnDimension('E')->setWidth(25); // Incomplete Subjects (Previous)
            $sheet->getColumnDimension('F')->setWidth(25); // Incomplete Subjects (Current)

            // Header Section
            $row = 1;
            $sheet->setCellValue('A' . $row, 'School Form 5 (SF 5) Report on Promotion & Level of Proficiency');
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $sheet->setCellValue('A' . $row, '(This replaces Forms 18-E1, 18-E2, 18A and List of Graduates)');
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row += 2;
            // School Information
            $sheet->setCellValue('A' . $row, 'School ID:');
            $sheet->setCellValue('B' . $row, '308041');
            $sheet->setCellValue('D' . $row, 'Region:');
            $sheet->setCellValue('E' . $row, 'IV-A');

            $row++;
            $sheet->setCellValue('A' . $row, 'School Name:');
            $sheet->setCellValue('B' . $row, 'Castañas National Highschool');
            $sheet->setCellValue('D' . $row, 'Division:');
            $sheet->setCellValue('E' . $row, 'Quezon Province');

            $row++;
            $sheet->setCellValue('A' . $row, 'District:');
            $sheet->setCellValue('B' . $row, 'Sariaya East');
            $sheet->setCellValue('D' . $row, 'School Year:');
            $sheet->setCellValue('E' . $row, $academicYear->name);

            $row++;
            $sheet->setCellValue('A' . $row, 'Curriculum:');
            $sheet->setCellValue('B' . $row, 'K to 12');
            $sheet->setCellValue('D' . $row, 'Grade Level:');
            $sheet->setCellValue('E' . $row, $section->yearLevel->name ?? 'N/A');

            $row++;
            $sheet->setCellValue('A' . $row, 'Section:');
            $sheet->setCellValue('B' . $row, $section->name);

            $row += 2;

            // Table Header
            $headerRow = $row;
            $sheet->setCellValue('A' . $row, 'LRN');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            $sheet->setCellValue('B' . $row, 'LEARNER\'S NAME (Last Name, First Name, Middle Name)');
            $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('B' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            $sheet->setCellValue('C' . $row, 'GENERAL AVERAGE');
            $sheet->getStyle('C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('C' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            $sheet->setCellValue('D' . $row, 'ACTION TAKEN');
            $sheet->getStyle('D' . $row)->getFont()->setBold(true);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('D' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            $sheet->setCellValue('E' . $row, 'INCOMPLETE SUBJECT/S');
            $sheet->mergeCells('E' . $row . ':F' . $row);
            $sheet->getStyle('E' . $row)->getFont()->setBold(true);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('E' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            $row++;
            $sheet->setCellValue('E' . $row, 'From previous school years completed as of end of current School Year');
            $sheet->getStyle('E' . $row)->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            $sheet->setCellValue('F' . $row, 'As of end of current School Year');
            $sheet->getStyle('F' . $row)->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            $row++;

            // Student rows
            $maleTotal = 0;
            $femaleTotal = 0;
            foreach ($promotionData as $data) {
                $sheet->setCellValue('A' . $row, $data['lrn']);
                $sheet->setCellValue('B' . $row, $data['name']);
                $sheet->setCellValue('C' . $row, $data['general_average'] . ($data['descriptive_grade'] ? ' (' . $data['descriptive_grade'] . ')' : ''));
                $sheet->setCellValue('D' . $row, $data['action_taken']);
                $sheet->setCellValue('E' . $row, $data['incomplete_subjects_previous']);
                $sheet->setCellValue('F' . $row, $data['incomplete_subjects_current']);

                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($data['gender'] === 'male') {
                    $maleTotal++;
                } else {
                    $femaleTotal++;
                }

                $row++;
            }

            // Summary rows
            $summaryStartRow = $row;
            $row++;
            $sheet->setCellValue('A' . $row, 'TOTAL MALE');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');
            $sheet->setCellValue('B' . $row, $maleTotal);

            $row++;
            $sheet->setCellValue('A' . $row, 'TOTAL FEMALE');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');
            $sheet->setCellValue('B' . $row, $femaleTotal);

            $row++;
            $sheet->setCellValue('A' . $row, 'COMBINED');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');
            $sheet->setCellValue('B' . $row, $maleTotal + $femaleTotal);

            // Apply borders
            $lastDataRow = $row;
            $range = 'A' . $headerRow . ':F' . $lastDataRow;
            $sheet->getStyle($range)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);

            // Summary Tables (Right side)
            $summaryTableRow = $lastDataRow + 3;
            $summaryTableCol = 'H';

            // Promotion Status Summary
            $sheet->setCellValue($summaryTableCol . $summaryTableRow, 'SUMMARY TABLE');
            $sheet->getStyle($summaryTableCol . $summaryTableRow)->getFont()->setBold(true)->setSize(11);
            $summaryTableRow++;
            $sheet->setCellValue($summaryTableCol . $summaryTableRow, 'STATUS');
            $sheet->getStyle($summaryTableCol . $summaryTableRow)->getFont()->setBold(true);
            $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 1);
            $sheet->setCellValue($nextCol . $summaryTableRow, 'MALE');
            $sheet->getStyle($nextCol . $summaryTableRow)->getFont()->setBold(true);
            $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 2);
            $sheet->setCellValue($nextCol . $summaryTableRow, 'FEMALE');
            $sheet->getStyle($nextCol . $summaryTableRow)->getFont()->setBold(true);
            $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 3);
            $sheet->setCellValue($nextCol . $summaryTableRow, 'TOTAL');
            $sheet->getStyle($nextCol . $summaryTableRow)->getFont()->setBold(true);
            $summaryTableRow++;

            $statuses = ['PROMOTED', 'IRREGULAR', 'RETAINED'];
            foreach ($statuses as $status) {
                $statusKey = strtolower($status);
                $sheet->setCellValue($summaryTableCol . $summaryTableRow, $status);
                $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 1);
                $sheet->setCellValue($nextCol . $summaryTableRow, $summaryStats[$statusKey]['male'] ?? 0);
                $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 2);
                $sheet->setCellValue($nextCol . $summaryTableRow, $summaryStats[$statusKey]['female'] ?? 0);
                $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 3);
                $sheet->setCellValue($nextCol . $summaryTableRow, $summaryStats[$statusKey]['total'] ?? 0);
                $summaryTableRow++;
            }

            // Level of Proficiency Summary
            $summaryTableRow += 2;
            $sheet->setCellValue($summaryTableCol . $summaryTableRow, 'LEVEL OF PROFICIENCY');
            $sheet->getStyle($summaryTableCol . $summaryTableRow)->getFont()->setBold(true)->setSize(11);
            $summaryTableRow++;
            $sheet->setCellValue($summaryTableCol . $summaryTableRow, 'Level');
            $sheet->getStyle($summaryTableCol . $summaryTableRow)->getFont()->setBold(true);
            $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 1);
            $sheet->setCellValue($nextCol . $summaryTableRow, 'MALE');
            $sheet->getStyle($nextCol . $summaryTableRow)->getFont()->setBold(true);
            $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 2);
            $sheet->setCellValue($nextCol . $summaryTableRow, 'FEMALE');
            $sheet->getStyle($nextCol . $summaryTableRow)->getFont()->setBold(true);
            $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 3);
            $sheet->setCellValue($nextCol . $summaryTableRow, 'TOTAL');
            $sheet->getStyle($nextCol . $summaryTableRow)->getFont()->setBold(true);
            $summaryTableRow++;

            $proficiencyLevels = [
                ['BEGINNING', 'beginning', '74% and below'],
                ['DEVELOPING', 'developing', '75%-79%'],
                ['APPROACHING PROFICIENCY', 'approaching', '80%-84%'],
                ['PROFICIENT', 'proficient', '85%-89%'],
                ['ADVANCED', 'advanced', '90% and above'],
            ];

            foreach ($proficiencyLevels as $level) {
                $sheet->setCellValue($summaryTableCol . $summaryTableRow, $level[0] . ' (' . $level[2] . ')');
                $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 1);
                $sheet->setCellValue($nextCol . $summaryTableRow, $summaryStats[$level[1]]['male'] ?? 0);
                $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 2);
                $sheet->setCellValue($nextCol . $summaryTableRow, $summaryStats[$level[1]]['female'] ?? 0);
                $nextCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($summaryTableCol) + 3);
                $sheet->setCellValue($nextCol . $summaryTableRow, $summaryStats[$level[1]]['total'] ?? 0);
                $summaryTableRow++;
            }

            // Guidelines and Signatures (Left side, below table)
            $guidelinesRow = $lastDataRow + 3;
            $sheet->setCellValue('A' . $guidelinesRow, 'GUIDELINES:');
            $sheet->getStyle('A' . $guidelinesRow)->getFont()->setBold(true)->setSize(11);
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '1. For All Grade/Year Levels');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '2. To be prepared by the Adviser. Final rating per subject area should be taken from the record of subject teachers. The class adviser should compute for the General Average.');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '3. On the summary table, reflect the total number of learners promoted, retained and *irregular (*for grade 7 onwards only) and the level of proficiency according to the individual General Average.');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '4. Must tally with the total enrollment report as of End of School Year GESP/GSSP (EBEIS)');
            $guidelinesRow++;
            $sheet->setCellValue('A' . $guidelinesRow, '5. Protocols of validation & submission is under the discretion of the Schools Division Superintendent');

            // Signatures
            $signatureRow = $guidelinesRow + 3;
            $sheet->setCellValue('A' . $signatureRow, 'PREPARED BY:');
            $sheet->getStyle('A' . $signatureRow)->getFont()->setBold(true);
            $signatureRow++;
            $sheet->setCellValue('A' . $signatureRow, 'Class Adviser');
            $signatureRow++;
            $sheet->setCellValue('A' . $signatureRow, '(Name and Signature)');
            $signatureRow += 2;
            $sheet->setCellValue('A' . $signatureRow, 'CERTIFIED CORRECT & SUBMITTED:');
            $sheet->getStyle('A' . $signatureRow)->getFont()->setBold(true);
            $signatureRow++;
            $sheet->setCellValue('A' . $signatureRow, 'School Head');
            $signatureRow++;
            $sheet->setCellValue('A' . $signatureRow, '(Name and Signature)');
            $signatureRow += 2;
            $sheet->setCellValue('A' . $signatureRow, 'REVIEWED BY:');
            $sheet->getStyle('A' . $signatureRow)->getFont()->setBold(true);
            $signatureRow++;
            $sheet->setCellValue('A' . $signatureRow, '(Name and Signature)');
            $signatureRow++;
            $sheet->setCellValue('A' . $signatureRow, 'Division Representative');

            // Footer
            $footerRow = $signatureRow + 2;
            $sheet->setCellValue('A' . $footerRow, 'School Form 5: Page ___ of ___');

            // Generate Excel file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'SF5_Promotion_Report_' . $section->name . '_' . $academicYear->name . '.xlsx';

            ob_start();
            $writer->save('php://output');
            $content = ob_get_contents();
            ob_end_clean();

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            Log::error('SF5 Excel Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export SF5 Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get descriptive grade from numeric average
     */
    private function getDescriptiveGrade($average)
    {
        if ($average >= 90) return 'A';
        if ($average >= 85) return 'P';
        if ($average >= 80) return 'AP';
        if ($average >= 75) return 'D';
        if ($average > 0) return 'B';
        return '';
    }
}
