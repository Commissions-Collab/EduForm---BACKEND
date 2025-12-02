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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

        $totalDays = $student->attendances_count ?? $student->attendances->count();
        $presentDays = $student->present_attendances_count ?? $student->attendances->where('status', 'present')->count();
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

    /**
     * Export SF5 Excel for SuperAdmin - Compiled per Year Level
     * Supports both section_id (single section) and year_level_id (all sections in year level)
     */
    public function exportSF5Excel(Request $request)
    {
        try {
            // Accept both section_id and year_level_id for backward compatibility
            $request->validate([
                'academic_year_id' => 'required|exists:academic_years,id',
            ]);

            $academicYearId = $request->get('academic_year_id');
            $academicYear = AcademicYear::findOrFail($academicYearId);

            $yearLevel = null;
            $sections = collect();
            $isCompiled = false;

            // Check if year_level_id is provided (new method - compiled report)
            if ($request->has('year_level_id')) {
                $request->validate([
                    'year_level_id' => 'required|exists:year_levels,id',
                ]);

                $yearLevelId = $request->get('year_level_id');
                $yearLevel = \App\Models\YearLevel::findOrFail($yearLevelId);

                // Get all sections for this year level
                $sections = Section::where('year_level_id', $yearLevelId)
                    ->where('academic_year_id', $academicYearId)
                    ->get();

                $isCompiled = true;
            }
            // Check if section_id is provided (old method - single section)
            elseif ($request->has('section_id')) {
                $request->validate([
                    'section_id' => 'required|exists:sections,id',
                ]);

                $sectionId = $request->get('section_id');
                $section = Section::with(['yearLevel'])->findOrFail($sectionId);
                $yearLevel = $section->yearLevel;

                // Get only this section
                $sections = collect([$section]);
                $isCompiled = false;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Either section_id or year_level_id is required'
                ], 422);
            }

            if ($sections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No sections found'
                ], 404);
            }

            $sectionIds = $sections->pluck('id')->toArray();

            // Get quarters and subjects
            $quarters = Quarter::where('academic_year_id', $academicYearId)
                ->orderBy('start_date')
                ->get();

            $subjects = Subject::whereHas('teacherSubjects', function ($query) use ($academicYearId) {
                $query->where('academic_year_id', $academicYearId);
            })->get();

            // Get enrolled students from ALL sections in this year level
            $students = Student::whereHas('enrollments', function ($query) use ($sectionIds, $academicYearId) {
                $query->whereIn('section_id', $sectionIds)
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
                    },
                    'enrollments' => function ($query) use ($academicYearId) {
                        $query->where('academic_year_id', $academicYearId);
                    }
                ])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            // Process student data (same as PromotionReportController)
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
                
                $actionTaken = 'RETAINED';
                if ($promotionStatus === 'Pass') {
                    $actionTaken = 'PROMOTED';
                } elseif ($promotionStatus === 'Incomplete') {
                    $actionTaken = 'IRREGULAR';
                }

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

                $generalAverage = $finalAverage > 0 ? number_format($finalAverage, 2) : '';
                $descriptiveGrade = $this->getDescriptiveGrade($finalAverage);

                $gender = strtolower($student->gender ?? 'male');
                $genderKey = $gender === 'female' ? 'female' : 'male';

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
                    'incomplete_subjects_previous' => '',
                    'incomplete_subjects_current' => '',
                    'gender' => $genderKey,
                ];
            }

            // Create spreadsheet (same format as PromotionReportController)
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SF5 Promotion Report');

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(35);
            $sheet->getColumnDimension('C')->setWidth(18);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(25);
            $sheet->getColumnDimension('F')->setWidth(25);

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
            $sheet->setCellValue('A' . $row, 'Region:');
            $sheet->setCellValue('B' . $row, '');
            $sheet->setCellValue('D' . $row, 'Division:');
            $sheet->setCellValue('E' . $row, '');

            $row++;
            $sheet->setCellValue('A' . $row, 'School ID:');
            $sheet->setCellValue('B' . $row, '');
            $sheet->setCellValue('D' . $row, 'School Year:');
            $sheet->setCellValue('E' . $row, $academicYear->name);

            $row++;
            $sheet->setCellValue('A' . $row, 'School Name:');
            $sheet->setCellValue('B' . $row, env('SCHOOL_NAME', 'AcadFlow School'));
            $sheet->setCellValue('D' . $row, 'District:');
            $sheet->setCellValue('E' . $row, '');

            $row++;
            $sheet->setCellValue('A' . $row, 'Curriculum:');
            $sheet->setCellValue('B' . $row, 'K to 12');
            $sheet->setCellValue('D' . $row, 'Grade Level:');
            $sheet->setCellValue('E' . $row, $yearLevel->name ?? 'N/A');

            $row++;
            $sheet->setCellValue('A' . $row, 'Section:');
            $sheet->setCellValue('B' . $row, $isCompiled ? 'All Sections (Compiled)' : $sections->first()->name);

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

            // Guidelines and Signatures
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
            $sectionName = $isCompiled ? $yearLevel->name : $sections->first()->name;
            $fileName = 'SF5_Promotion_Report_' . $sectionName . '_' . $academicYear->name . '.xlsx';

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
     * Export SF6 Excel for SuperAdmin - Compiled per Year Level or Overall Academic Year
     * SF6 is Learner Progress Report showing subject grades per quarter
     * Supports section_id (single section), year_level_id (all sections in year level), or overall academic year
     */
    public function exportSF6Excel(Request $request)
    {
        // Increase memory and time limits for large exports
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        
        try {
            // Accept both section_id and year_level_id for backward compatibility, or export for entire academic year
            $request->validate([
                'academic_year_id' => 'required|exists:academic_years,id',
            ]);

            $academicYearId = $request->get('academic_year_id');
            $academicYear = AcademicYear::findOrFail($academicYearId);

            $yearLevel = null;
            $sections = collect();
            $isCompiled = false;
            $isOverallAcademicYear = false;

            // Get section_id and year_level_id values, checking if they're actually set and not empty
            $sectionId = $request->input('section_id');
            $yearLevelId = $request->input('year_level_id');
            
            // Normalize values - convert empty strings, null, '0', or 0 to null
            $sectionId = ($sectionId && $sectionId !== '' && $sectionId !== '0' && $sectionId !== 0) ? $sectionId : null;
            $yearLevelId = ($yearLevelId && $yearLevelId !== '' && $yearLevelId !== '0' && $yearLevelId !== 0) ? $yearLevelId : null;
            
            // Check if year_level_id is provided (new method - compiled report)
            if ($yearLevelId) {
                $request->validate([
                    'year_level_id' => 'required|exists:year_levels,id',
                ]);

                $yearLevel = \App\Models\YearLevel::findOrFail($yearLevelId);

                // Get all sections for this year level
                $sections = Section::where('year_level_id', $yearLevelId)
                    ->where('academic_year_id', $academicYearId)
                    ->get();

                $isCompiled = true;
            }
            // Check if section_id is provided (old method - single section)
            elseif ($sectionId) {
                $request->validate([
                    'section_id' => 'required|exists:sections,id',
                ]);

                $section = Section::with(['yearLevel'])->findOrFail($sectionId);
                $yearLevel = $section->yearLevel;

                // Get only this section
                $sections = collect([$section]);
                $isCompiled = false;
            } else {
                // Export for entire academic year - all sections, all year levels
                $isOverallAcademicYear = true;
                $sections = Section::where('academic_year_id', $academicYearId)->get();
            }

            if ($sections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No sections found'
                ], 404);
            }

            $sectionIds = $sections->pluck('id')->toArray();

            // Get quarters and subjects
            $quarters = Quarter::where('academic_year_id', $academicYearId)
                ->orderBy('start_date')
                ->get();

            $subjects = Subject::whereHas('teacherSubjects', function ($query) use ($academicYearId) {
                $query->where('academic_year_id', $academicYearId);
            })->orderBy('name')->get();

            if ($subjects->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subjects found for this academic year'
                ], 404);
            }

            // Get enrolled students from ALL sections (or all sections for overall academic year)
            // Ensure sectionIds is not empty before querying
            if (empty($sectionIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid sections found'
                ], 404);
            }

            $studentQuery = Student::whereHas('enrollments', function ($query) use ($sectionIds, $academicYearId) {
                $query->whereIn('section_id', $sectionIds)
                    ->where('academic_year_id', $academicYearId)
                    ->where('enrollment_status', 'enrolled');
            });

            // Validate we have data to export
            if ($studentQuery->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No enrolled students found for the selected criteria'
                ], 404);
            }

            if ($quarters->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No quarters found for this academic year'
                ], 404);
            }

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SF6 Learner Progress');

            // Calculate column widths
            $colWidths = [
                'A' => 12, // LRN
                'B' => 35, // Learner's Name
            ];
            $col = 'C';
            foreach ($quarters as $quarter) {
                foreach ($subjects as $subject) {
                    $colWidths[$col] = 10;
                    $col++;
                }
            }
            $colWidths[$col] = 15; // Final Average
            $col++;
            $colWidths[$col] = 12; // Action Taken

            foreach ($colWidths as $colLetter => $width) {
                $sheet->getColumnDimension($colLetter)->setWidth($width);
            }

            // Header Section
            $row = 1;
            $sheet->setCellValue('A' . $row, 'School Form 6 (SF 6) Learner Progress Report');
            // Calculate last column: A(1) + B(1) + subject columns + Final Average(1) + Action Taken(1)
            // Starting from C (index 3), we have: quarters * subjects + 2 more columns (Final Average + Action Taken)
            $lastColIndex = 3 + count($quarters) * count($subjects) + 2; // +2 for Final Average and Action Taken
            $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $sheet->setCellValue('A' . $row, '(This replaces Form 18-E1, 18-E2, 18A and List of Graduates)');
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // School Information
            // For overall academic year: show school info but exclude Grade Level and Section
            // For specific section/year level: show all school info including Grade Level and Section
            $requestSectionId = $request->input('section_id');
            $requestYearLevelId = $request->input('year_level_id');
            $hasValidSection = ($requestSectionId && $requestSectionId !== '' && $requestSectionId !== '0' && $requestSectionId !== 0);
            $hasValidYearLevel = ($requestYearLevelId && $requestYearLevelId !== '' && $requestYearLevelId !== '0' && $requestYearLevelId !== 0);
            
            // Always show school information (for both overall and specific exports)
            $row++;
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
            
            // Only show Grade Level and Section when NOT exporting overall academic year
            if (!$isOverallAcademicYear && ($hasValidSection || $hasValidYearLevel)) {
                $sheet->setCellValue('D' . $row, 'Grade Level:');
                $sheet->setCellValue('E' . $row, $yearLevel->name ?? 'N/A');

                $row++;
                $sheet->setCellValue('A' . $row, 'Section:');
                $sheet->setCellValue('B' . $row, $isCompiled ? 'All Sections (Compiled)' : $sections->first()->name);
            }

            // Initialize Summary Stats - support per grade level when exporting overall academic year
            $summaryStats = [
                'PROMOTED' => ['M' => 0, 'F' => 0, 'GRADE_7' => ['M' => 0, 'F' => 0], 'GRADE_8' => ['M' => 0, 'F' => 0], 'GRADE_9' => ['M' => 0, 'F' => 0], 'GRADE_10' => ['M' => 0, 'F' => 0]],
                'IRREGULAR' => ['M' => 0, 'F' => 0, 'GRADE_7' => ['M' => 0, 'F' => 0], 'GRADE_8' => ['M' => 0, 'F' => 0], 'GRADE_9' => ['M' => 0, 'F' => 0], 'GRADE_10' => ['M' => 0, 'F' => 0]],
                'RETAINED' => ['M' => 0, 'F' => 0, 'GRADE_7' => ['M' => 0, 'F' => 0], 'GRADE_8' => ['M' => 0, 'F' => 0], 'GRADE_9' => ['M' => 0, 'F' => 0], 'GRADE_10' => ['M' => 0, 'F' => 0]],
                'BEGINNING' => ['M' => 0, 'F' => 0, 'GRADE_7' => ['M' => 0, 'F' => 0], 'GRADE_8' => ['M' => 0, 'F' => 0], 'GRADE_9' => ['M' => 0, 'F' => 0], 'GRADE_10' => ['M' => 0, 'F' => 0]],
                'DEVELOPING' => ['M' => 0, 'F' => 0, 'GRADE_7' => ['M' => 0, 'F' => 0], 'GRADE_8' => ['M' => 0, 'F' => 0], 'GRADE_9' => ['M' => 0, 'F' => 0], 'GRADE_10' => ['M' => 0, 'F' => 0]],
                'APPROACHING' => ['M' => 0, 'F' => 0, 'GRADE_7' => ['M' => 0, 'F' => 0], 'GRADE_8' => ['M' => 0, 'F' => 0], 'GRADE_9' => ['M' => 0, 'F' => 0], 'GRADE_10' => ['M' => 0, 'F' => 0]],
                'PROFICIENT' => ['M' => 0, 'F' => 0, 'GRADE_7' => ['M' => 0, 'F' => 0], 'GRADE_8' => ['M' => 0, 'F' => 0], 'GRADE_9' => ['M' => 0, 'F' => 0], 'GRADE_10' => ['M' => 0, 'F' => 0]],
                'ADVANCED' => ['M' => 0, 'F' => 0, 'GRADE_7' => ['M' => 0, 'F' => 0], 'GRADE_8' => ['M' => 0, 'F' => 0], 'GRADE_9' => ['M' => 0, 'F' => 0], 'GRADE_10' => ['M' => 0, 'F' => 0]],
            ];

            // Calculate stats using chunking to save memory
            $studentQuery->orderBy('last_name')
                ->orderBy('first_name')
                ->chunk(100, function ($students) use (&$summaryStats, $subjects, $quarters, $academicYearId, $isOverallAcademicYear) {
                    // Load relationships for this chunk
                    $students->load([
                        'grades' => function ($query) use ($academicYearId) {
                            $query->whereHas('quarter', function ($q) use ($academicYearId) {
                                $q->where('academic_year_id', $academicYearId);
                            });
                        },
                        'enrollments' => function ($query) use ($academicYearId) {
                            $query->where('academic_year_id', $academicYearId)
                                ->with('section.yearLevel');
                        }
                    ]);

                    // Optimize attendance counting
                    $students->loadCount([
                        'attendances' => function ($query) use ($academicYearId) {
                            $query->whereHas('academicYear', function ($q) use ($academicYearId) {
                                $q->where('id', $academicYearId);
                            });
                        },
                        'attendances as present_attendances_count' => function ($query) use ($academicYearId) {
                            $query->whereHas('academicYear', function ($q) use ($academicYearId) {
                                $q->where('id', $academicYearId);
                            })->where('status', 'present');
                        }
                    ]);

                    foreach ($students as $student) {
                        $studentData = $this->calculateStudentPromotion($student, $subjects, $quarters);

                        $finalAverage = $studentData['final_average'] ?? 0;
                        $promotionStatus = $studentData['promotion_status'] ?? 'Incomplete';

                        $actionTaken = 'RETAINED';
                        if ($promotionStatus === 'Pass') {
                            $actionTaken = 'PROMOTED';
                        } elseif ($promotionStatus === 'Incomplete') {
                            $actionTaken = 'IRREGULAR';
                        }

                        // Update Stats
                        $gender = strtoupper(substr($student->gender ?? 'M', 0, 1)); // Default to M if null, take first char
                        if ($gender !== 'M' && $gender !== 'F') $gender = 'M'; // Fallback

                        // Get student's year level for overall academic year export
                        $studentYearLevel = null;
                        if ($isOverallAcademicYear) {
                            $enrollment = $student->enrollments->where('academic_year_id', $academicYearId)->first();
                            if ($enrollment && $enrollment->section) {
                                $section = $enrollment->section;
                                // Access yearLevel - should be loaded via ->with('section.yearLevel')
                                $studentYearLevel = $section->yearLevel ?? null;
                            }
                        }

                        // Determine grade level key
                        $gradeKey = null;
                        if ($isOverallAcademicYear && $studentYearLevel) {
                            $yearLevelName = strtoupper($studentYearLevel->name ?? '');
                            if (str_contains($yearLevelName, '7')) $gradeKey = 'GRADE_7';
                            elseif (str_contains($yearLevelName, '8')) $gradeKey = 'GRADE_8';
                            elseif (str_contains($yearLevelName, '9')) $gradeKey = 'GRADE_9';
                            elseif (str_contains($yearLevelName, '10')) $gradeKey = 'GRADE_10';
                        }

                        // Status Stats
                        if (isset($summaryStats[$actionTaken])) {
                            $summaryStats[$actionTaken][$gender]++;
                            if ($gradeKey) {
                                $summaryStats[$actionTaken][$gradeKey][$gender]++;
                            }
                        }

                        // Proficiency Stats
                        if ($finalAverage > 0) {
                            $proficiencyKey = null;
                            if ($finalAverage >= 90) $proficiencyKey = 'ADVANCED';
                            elseif ($finalAverage >= 85) $proficiencyKey = 'PROFICIENT';
                            elseif ($finalAverage >= 80) $proficiencyKey = 'APPROACHING';
                            elseif ($finalAverage >= 75) $proficiencyKey = 'DEVELOPING';
                            else $proficiencyKey = 'BEGINNING';

                            if ($proficiencyKey) {
                                $summaryStats[$proficiencyKey][$gender]++;
                                if ($gradeKey) {
                                    $summaryStats[$proficiencyKey][$gradeKey][$gender]++;
                                }
                            }
                        }
                    }
                });

            // SUMMARY TABLE
            $row += 2;
            $summaryStartRow = $row;
            
            // Header Row 1
            $sheet->setCellValue('A' . $row, 'SUMMARY TABLE');
            $sheet->mergeCells('A' . $row . ':A' . ($row + 1));
            
            $grades = [
                'GRADE 7' => 'B',
                'GRADE 8' => 'E',
                'GRADE 9' => 'H',
                'GRADE 10' => 'K',
                'TOTAL' => 'N'
            ];

            foreach ($grades as $label => $col) {
                $sheet->setCellValue($col . $row, $label);
                $endCol = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 2);
                $sheet->mergeCells($col . $row . ':' . $endCol . $row);
                
                // Header Row 2 (M/F/Total)
                $sheet->setCellValue($col . ($row + 1), 'MALE');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 1) . ($row + 1), 'FEMALE');
                $sheet->setCellValue($endCol . ($row + 1), 'TOTAL');
            }
            
            // Style Headers
            $sheet->getStyle('A' . $row . ':P' . ($row + 1))->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle('A' . $row . ':P' . ($row + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A' . $row . ':P' . ($row + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $row += 2;

            // Determine current grade column(s)
            $targetCols = [];
            if ($isOverallAcademicYear) {
                // For overall academic year, show all grade levels
                $targetCols = ['B' => 'GRADE_7', 'E' => 'GRADE_8', 'H' => 'GRADE_9', 'K' => 'GRADE_10'];
            } else {
                // For specific section/year level, show only that grade level
                $currentGradeName = strtoupper($yearLevel->name ?? '');
                $targetCol = null;
                if (str_contains($currentGradeName, '7')) $targetCol = 'B';
                elseif (str_contains($currentGradeName, '8')) $targetCol = 'E';
                elseif (str_contains($currentGradeName, '9')) $targetCol = 'H';
                elseif (str_contains($currentGradeName, '10')) $targetCol = 'K';
                
                if ($targetCol) {
                    $targetCols[$targetCol] = null; // No grade key needed for single grade level
                }
            }

            // Helper to fill row
            $fillSummaryRow = function($label, $statKey, $isHeader = false) use (&$sheet, &$row, $targetCols, $summaryStats, $isOverallAcademicYear) {
                $sheet->setCellValue('A' . $row, $label);
                if ($isHeader) {
                     // Repeat M/F/Total headers (Grade 7, 8, 9, 10, TOTAL)
                     $cols = ['B', 'E', 'H', 'K', 'N'];
                     foreach ($cols as $col) {
                        $sheet->setCellValue($col . $row, 'MALE');
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 1) . $row, 'FEMALE');
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 2) . $row, 'TOTAL');
                     }
                     $sheet->getStyle('A' . $row . ':P' . $row)->getFont()->setBold(true)->setSize(9);
                } else {
                    if ($statKey && !empty($targetCols)) {
                        $totalM = 0;
                        $totalF = 0;
                        
                        // Fill data for each target column
                        foreach ($targetCols as $col => $gradeKey) {
                            if ($isOverallAcademicYear && $gradeKey) {
                                // For overall academic year, use grade-specific stats
                                $m = $summaryStats[$statKey][$gradeKey]['M'] ?? 0;
                                $f = $summaryStats[$statKey][$gradeKey]['F'] ?? 0;
                            } else {
                                // For single grade level, use overall stats
                                $m = $summaryStats[$statKey]['M'] ?? 0;
                                $f = $summaryStats[$statKey]['F'] ?? 0;
                            }
                            $t = $m + $f;
                            
                            $sheet->setCellValue($col . $row, $m);
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 1) . $row, $f);
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 2) . $row, $t);
                            
                            $totalM += $m;
                            $totalF += $f;
                        }
                        
                        // Fill Total Column (N, O, P)
                        $totalT = $totalM + $totalF;
                        $sheet->setCellValue('N' . $row, $totalM);
                        $sheet->setCellValue('O' . $row, $totalF);
                        $sheet->setCellValue('P' . $row, $totalT);
                    }
                }
                $sheet->getStyle('A' . $row . ':P' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A' . $row . ':P' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // Label left aligned
                $row++;
            };

            $fillSummaryRow('PROMOTED', 'PROMOTED');
            $fillSummaryRow('IRREGULAR', 'IRREGULAR');
            $fillSummaryRow('RETAINED', 'RETAINED');
            
            $fillSummaryRow('LEVEL OF PROFICIENCY', null, true);
            
            $fillSummaryRow('BEGINNING (B: 74% and below)', 'BEGINNING');
            $fillSummaryRow('DEVELOPING (D: 75%-79%)', 'DEVELOPING');
            $fillSummaryRow('APPROACHING PROFICIENCY (AP: 80%-84%)', 'APPROACHING');
            $fillSummaryRow('PROFICIENT (P: 85% -89%)', 'PROFICIENT');
            $fillSummaryRow('ADVANCED (A: 90% and above)', 'ADVANCED');
            
            // Total Row
            $sheet->setCellValue('A' . $row, 'TOTAL');
            if (!empty($targetCols)) {
                $grandTotalM = 0;
                $grandTotalF = 0;
                
                // Calculate totals for each grade level column
                foreach ($targetCols as $col => $gradeKey) {
                    $colTotalM = 0;
                    $colTotalF = 0;
                    
                    foreach(['BEGINNING', 'DEVELOPING', 'APPROACHING', 'PROFICIENT', 'ADVANCED'] as $key) {
                        if ($isOverallAcademicYear && $gradeKey) {
                            $colTotalM += $summaryStats[$key][$gradeKey]['M'] ?? 0;
                            $colTotalF += $summaryStats[$key][$gradeKey]['F'] ?? 0;
                        } else {
                            $colTotalM += $summaryStats[$key]['M'] ?? 0;
                            $colTotalF += $summaryStats[$key]['F'] ?? 0;
                        }
                    }
                    
                    $colTotalT = $colTotalM + $colTotalF;
                    $sheet->setCellValue($col . $row, $colTotalM);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 1) . $row, $colTotalF);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 2) . $row, $colTotalT);
                    
                    $grandTotalM += $colTotalM;
                    $grandTotalF += $colTotalF;
                }
                
                // Fill Total Column (N, O, P)
                $grandTotalT = $grandTotalM + $grandTotalF;
                $sheet->setCellValue('N' . $row, $grandTotalM);
                $sheet->setCellValue('O' . $row, $grandTotalF);
                $sheet->setCellValue('P' . $row, $grandTotalT);
            }
            $sheet->getStyle('A' . $row . ':P' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A' . $row . ':P' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':P' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $row++;

            // Footer
            $footerRow = $row + 1;
            $sheet->setCellValue('A' . $footerRow, 'School Form 6: Page ___ of ___');

            // Generate Excel file
            try {
                $writer = new Xlsx($spreadsheet);
                if ($isOverallAcademicYear) {
                    $fileName = 'SF6_Learner_Progress_Overall_' . $academicYear->name . '.xlsx';
                } else {
                    $sectionName = $isCompiled ? ($yearLevel->name ?? 'N/A') : ($sections->first()->name ?? 'N/A');
                    $fileName = 'SF6_Learner_Progress_' . $sectionName . '_' . $academicYear->name . '.xlsx';
                }

                // Clear any previous output
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                ob_start();
                $writer->save('php://output');
                $content = ob_get_contents();
                ob_end_clean();

                return response($content)
                    ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                    ->header('Cache-Control', 'max-age=0')
                    ->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            } catch (\Exception $excelError) {
                // Clean output buffer if error occurred
                if (ob_get_level()) {
                    ob_end_clean();
                }
                throw $excelError; // Re-throw to be caught by outer catch
            }
        } catch (\Exception $e) {
            // Clean output buffer if error occurred
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            Log::error('SF6 Excel Export Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'academic_year_id' => $request->input('academic_year_id'),
                'section_id' => $request->input('section_id'),
                'year_level_id' => $request->input('year_level_id'),
            ]);
            
            // Return JSON error response with CORS headers (frontend will handle this)
            return response()->json([
                'success' => false,
                'message' => 'Failed to export SF6 Excel: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while exporting the file'
            ], 500)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
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
