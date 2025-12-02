<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\YearLevel;
use App\Models\AcademicYear;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class EnrollmentController extends Controller
{
    // Get all enrollments with pagination
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 25);

        try {
            $enrollments = Enrollment::with([
                'student:id,lrn,first_name,middle_name,last_name,gender',
                'yearLevel:id,name',
                'section:id,name,year_level_id,academic_year_id',
                'academicYear:id,name'
            ])
                ->latest()
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $enrollments
            ]);
        } catch (\Exception $e) {
            Log::error('Enrollment fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch enrollments'
            ], 500);
        }
    }

    // Get all students for enrollment dropdowns
    public function getStudents(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 1000);

        try {
            $students = Student::select('id', 'lrn', 'first_name', 'middle_name', 'last_name', 'gender')
                ->orderBy('first_name')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $students
            ]);
        } catch (\Exception $e) {
            Log::error('Students fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch students'
            ], 500);
        }
    }

    // Get academic years
    public function getAcademicYears(Request $request)
{
    $page = $request->get('page', 1);
    $perPage = $request->get('per_page', 100);

    try {
        $years = AcademicYear::select('id', 'name', 'is_current')  // Make sure this line is correct
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    } catch (\Exception $e) {
        Log::error('Academic years fetch error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch academic years'
        ], 500);
    }
}

    // Get year levels
    public function getYearLevels(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 100);

        try {
            $levels = YearLevel::select('id', 'name', 'code')
                ->orderBy('sort_order')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $levels
            ]);
        } catch (\Exception $e) {
            Log::error('Year levels fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch year levels'
            ], 500);
        }
    }

    // Get sections
    public function getSections(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 100);

        try {
            $sections = Section::select('id', 'name', 'year_level_id', 'academic_year_id')
                ->with([
                    'yearLevel:id,name',
                    'academicYear:id,name'
                ])
                ->orderBy('name')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $sections
            ]);
        } catch (\Exception $e) {
            Log::error('Sections fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sections'
            ], 500);
        }
    }

    // Store new enrollment
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => [
                'required',
                'exists:students,id',
                Rule::unique('enrollments')->where(function ($query) use ($request) {
                    return $query->where('academic_year_id', $request->academic_year_id)
                        ->where('enrollment_status', 'enrolled');
                }),
            ],
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade_level' => 'required|exists:year_levels,id',
            'section_id' => 'required|exists:sections,id',
            'enrollment_status' => 'required|string|in:enrolled,pending,withdrawn,transferred',
        ], [
            'student_id.unique' => "Student is already enrolled in this academic year."
        ]);

        try {
            $enrollment = Enrollment::create($validated);

            // Load relationships for response
            $enrollment->load([
                'student:id,lrn,first_name,middle_name,last_name,gender',
                'yearLevel:id,name',
                'section:id,name,year_level_id,academic_year_id',
                'academicYear:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Student enrolled successfully.',
                'data' => $enrollment
            ], 201);
        } catch (\Exception $e) {
            Log::error('Enrollment creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create enrollment.'
            ], 500);
        }
    }

    // Bulk store enrollments
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|exists:students,id',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'grade_level' => 'required|integer|exists:year_levels,id',
            'section_id' => 'required|integer|exists:sections,id',
        ]);

        $alreadyEnrolledStudents = [];
        $enrollmentsToCreate = [];

        foreach ($validated['student_ids'] as $studentId) {
            // Check if student is already enrolled in this academic year
            $alreadyEnrolled = Enrollment::where('student_id', $studentId)
                ->where('academic_year_id', $validated['academic_year_id'])
                ->where('enrollment_status', 'enrolled')
                ->exists();

            if ($alreadyEnrolled) {
                $student = Student::select('id', 'first_name', 'last_name')->find($studentId);
                $studentName = $student ? "{$student->first_name} {$student->last_name}" : "ID {$studentId}";
                $alreadyEnrolledStudents[] = $studentName;
            } else {
                $enrollmentsToCreate[] = [
                    'student_id' => $studentId,
                    'academic_year_id' => $validated['academic_year_id'],
                    'grade_level' => $validated['grade_level'],
                    'section_id' => $validated['section_id'],
                    'enrollment_status' => 'enrolled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($alreadyEnrolledStudents)) {
            return response()->json([
                'success' => false,
                'message' => 'The following students are already enrolled: ' . implode(', ', $alreadyEnrolledStudents)
            ], 422);
        }

        if (empty($enrollmentsToCreate)) {
            return response()->json([
                'success' => false,
                'message' => 'No students available for enrollment.'
            ], 422);
        }

        try {
            Enrollment::insert($enrollmentsToCreate);

            return response()->json([
                'success' => true,
                'message' => 'Students enrolled successfully.',
                'count' => count($enrollmentsToCreate)
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk enrollment error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while enrolling students.'
            ], 500);
        }
    }

    // Show specific enrollment
    public function show(string $id)
    {
        try {
            $enrollment = Enrollment::with([
                'student:id,lrn,first_name,middle_name,last_name,gender',
                'yearLevel:id,name',
                'section:id,name,year_level_id,academic_year_id',
                'academicYear:id,name'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $enrollment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enrollment not found.'
            ], 404);
        }
    }

    // Update enrollment
    public function update(Request $request, $id)
    {
        try {
            $enrollment = Enrollment::findOrFail($id);

            $validated = $request->validate([
                'academic_year_id' => 'sometimes|numeric|exists:academic_years,id',
                'grade_level' => 'sometimes|exists:year_levels,id',
                'section_id' => 'sometimes|exists:sections,id',
                'enrollment_status' => 'sometimes|string|in:enrolled,pending,withdrawn,transferred'
            ]);

            $enrollment->update($validated);

            // Load relationships for response
            $enrollment->load([
                'student:id,lrn,first_name,middle_name,last_name,gender',
                'yearLevel:id,name',
                'section:id,name,year_level_id,academic_year_id',
                'academicYear:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Enrollment updated successfully',
                'data' => $enrollment
            ]);
        } catch (\Exception $e) {
            Log::error('Enrollment update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update enrollment.'
            ], 500);
        }
    }

    // Delete enrollment
    public function destroy(string $id)
    {
        try {
            $enrollment = Enrollment::findOrFail($id);
            $enrollment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Enrollment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Enrollment deletion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete enrollment.'
            ], 500);
        }
    }

    // Promote students
    public function promote(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|exists:students,id',
            'next_academic_year_id' => 'required|integer|exists:academic_years,id',
            'next_grade_level_id' => 'required|integer|exists:year_levels,id',
            'section_id' => 'required|integer|exists:sections,id',
        ]);

        $promotionsToCreate = [];
        $errors = [];

        foreach ($validated['student_ids'] as $studentId) {
            // Check if student is already enrolled in target academic year
            $alreadyEnrolled = Enrollment::where('student_id', $studentId)
                ->where('academic_year_id', $validated['next_academic_year_id'])
                ->exists();

            if ($alreadyEnrolled) {
                $student = Student::select('id', 'first_name', 'last_name')->find($studentId);
                $studentName = $student ? "{$student->first_name} {$student->last_name}" : "ID {$studentId}";
                $errors[] = "{$studentName} is already enrolled in the target academic year.";
                continue;
            }

            $promotionsToCreate[] = [
                'student_id' => $studentId,
                'academic_year_id' => $validated['next_academic_year_id'],
                'grade_level' => $validated['next_grade_level_id'],
                'section_id' => $validated['section_id'],
                'enrollment_status' => 'enrolled',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Some students could not be promoted: ' . implode(' ', $errors)
            ], 422);
        }

        if (empty($promotionsToCreate)) {
            return response()->json([
                'success' => false,
                'message' => 'No students available for promotion.'
            ], 422);
        }

        try {
            Enrollment::insert($promotionsToCreate);

            return response()->json([
                'success' => true,
                'message' => 'Students promoted successfully.',
                'count' => count($promotionsToCreate)
            ]);
        } catch (\Exception $e) {
            Log::error('Student promotion error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while promoting students.'
            ], 500);
        }
    }

    /**
     * Export SF1 Excel - School Register
     * This exports student enrollment data in SF1 format
     * Supports compiled report per year level and multi-grade tables
     */
    public function exportSF1Excel(Request $request)
    {
        try {
            $request->validate([
                'academic_year_id' => 'required|exists:academic_years,id',
                'section_id' => 'nullable|exists:sections,id',
                'grade_level' => 'nullable|exists:year_levels,id',
                'year_level_id' => 'nullable|exists:year_levels,id', // Alias for grade_level
            ]);

            $academicYearId = $request->get('academic_year_id');
            $sectionId = $request->get('section_id');
            $gradeLevelId = $request->get('grade_level') ?? $request->get('year_level_id');

            $academicYear = AcademicYear::findOrFail($academicYearId);
            
            // Calculate age as of June 1st of the Academic Year
            $startYear = (int) explode('-', $academicYear->name)[0];
            $juneFirst = Carbon::create($startYear, 6, 1);

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SF1 School Register');

            // Set column widths
            $colWidths = [
                'A' => 12, 'B' => 15, 'C' => 15, 'D' => 15, 'E' => 6,  'F' => 12,
                'G' => 8,  'H' => 12, 'I' => 12, 'J' => 12, 'K' => 20, 'L' => 15,
                'M' => 15, 'N' => 15, 'O' => 20, 'P' => 15, 'Q' => 15, 'R' => 20,
                'S' => 15, 'T' => 15, 'U' => 20, 'V' => 12, 'W' => 15, 'X' => 30,
            ];

            foreach ($colWidths as $col => $width) {
                $sheet->getColumnDimension($col)->setWidth($width);
            }

            $row = 1;
            
            // Determine export mode: single section, single grade, or multi-grade
            if ($sectionId) {
                // Single section export
                $section = Section::with('yearLevel')->findOrFail($sectionId);
                $this->renderSF1GradeTable($sheet, $row, $academicYear, $section->yearLevel, $sectionId, $juneFirst);
            } elseif ($gradeLevelId) {
                // Single grade level export
                $yearLevel = YearLevel::findOrFail($gradeLevelId);
                $this->renderSF1GradeTable($sheet, $row, $academicYear, $yearLevel, null, $juneFirst);
            } else {
                // Multi-grade export (Grades 7, 8, 9, 10)
                $gradeNumbers = [7, 8, 9, 10];
                $firstTable = true;
                
                foreach ($gradeNumbers as $gradeNum) {
                    $yearLevel = YearLevel::where('name', 'LIKE', "%{$gradeNum}%")
                        ->orWhere('name', 'LIKE', "%Grade {$gradeNum}%")
                        ->first();
                    
                    if ($yearLevel) {
                        if (!$firstTable) {
                            $row += 5; // Spacing between grade tables
                        }
                        $this->renderSF1GradeTable($sheet, $row, $academicYear, $yearLevel, null, $juneFirst);
                        $firstTable = false;
                    }
                }
            }

            // Generate Excel file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'SF1_School_Register_' . $academicYear->name . '.xlsx';

            ob_start();
            $writer->save('php://output');
            $content = ob_get_contents();
            ob_end_clean();

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            Log::error('SF1 Excel Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export SF1 Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Render a single grade table in SF1 format
     */
    private function renderSF1GradeTable($sheet, &$row, $academicYear, $yearLevel, $sectionId, $juneFirst)
    {
        // Header Section
        $startRow = $row;
        
        $sheet->setCellValue('A' . $row, 'School Form 1 (SF 1) School Register');
        $sheet->mergeCells('A' . $row . ':X' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
        $sheet->setCellValue('A' . $row, '(This replaces Form 1, Master List & STS Form 2-Family Background and Profile)');
        $sheet->mergeCells('A' . $row . ':X' . $row);
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
        $sheet->setCellValue('A' . $row, 'Grade Level:');
        $sheet->setCellValue('B' . $row, $yearLevel->name);
        $sheet->setCellValue('D' . $row, 'Section:');
        
        if ($sectionId) {
            $section = Section::find($sectionId);
            $sheet->setCellValue('E' . $row, $section ? $section->name : '');
        } else {
            $sheet->setCellValue('E' . $row, 'All Sections (Compiled)');
        }

        $row += 2;

        // Table Header
        $headerRow = $row;
        
        // First header row
        $sheet->setCellValue('A' . $row, 'LRN');
        $sheet->setCellValue('B' . $row, 'NAME');
        $sheet->mergeCells('B' . $row . ':D' . $row);
        $sheet->setCellValue('E' . $row, 'Sex');
        $sheet->setCellValue('F' . $row, 'BIRTH DATE');
        $sheet->setCellValue('G' . $row, 'AGE as of June 1st');
        $sheet->setCellValue('H' . $row, 'MOTHER TONGUE');
        $sheet->setCellValue('I' . $row, 'IP (Ethnic Group)');
        $sheet->setCellValue('J' . $row, 'RELIGION');
        $sheet->setCellValue('K' . $row, 'ADDRESS');
        $sheet->mergeCells('K' . $row . ':N' . $row);
        $sheet->setCellValue('O' . $row, 'PARENTS');
        $sheet->mergeCells('O' . $row . ':T' . $row);
        $sheet->setCellValue('U' . $row, 'GUARDIAN (If not Parent)');
        $sheet->mergeCells('U' . $row . ':V' . $row);
        $sheet->setCellValue('W' . $row, 'Contact Number of Parent or Guardian');
        $sheet->setCellValue('X' . $row, 'REMARKS');

        // Second header row
        $row++;
        $sheet->setCellValue('B' . $row, '(Last Name)');
        $sheet->setCellValue('C' . $row, '(First Name)');
        $sheet->setCellValue('D' . $row, '(Middle Name)');
        $sheet->setCellValue('K' . $row, 'House #/Street/Sitio/Purok');
        $sheet->setCellValue('L' . $row, 'Barangay');
        $sheet->setCellValue('M' . $row, 'Municipality/City');
        $sheet->setCellValue('N' . $row, 'Province');
        $sheet->setCellValue('O' . $row, 'Father\'s Name');
        $sheet->mergeCells('O' . $row . ':Q' . $row);
        $sheet->setCellValue('R' . $row, 'Mother\'s Maiden Name');
        $sheet->mergeCells('R' . $row . ':T' . $row);
        $sheet->setCellValue('U' . $row, 'Name');
        $sheet->setCellValue('V' . $row, 'Relationship');

        // Third header row
        $row++;
        $sheet->setCellValue('O' . $row, '(Last Name)');
        $sheet->setCellValue('P' . $row, '(First Name)');
        $sheet->setCellValue('Q' . $row, '(Middle Name)');
        $sheet->setCellValue('R' . $row, '(Last Name)');
        $sheet->setCellValue('S' . $row, '(First Name)');
        $sheet->setCellValue('T' . $row, '(Middle Name)');

        // Apply header styling
        $headerRange = 'A' . $headerRow . ':X' . $row;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        $row++;

        // Get enrollments for this grade
        $enrollmentsQuery = Enrollment::with(['student', 'section'])
            ->where('academic_year_id', $academicYear->id)
            ->where('grade_level', $yearLevel->id)
            ->where('enrollment_status', 'enrolled');

        if ($sectionId) {
            $enrollmentsQuery->where('section_id', $sectionId);
        }

        $enrollments = $enrollmentsQuery->orderBy('section_id')
            ->orderByRaw('(SELECT last_name FROM students WHERE students.id = enrollments.student_id)')
            ->get();

        // Data rows
        $totalMale = 0;
        $totalFemale = 0;
        $currentSectionId = null;

        foreach ($enrollments as $enrollment) {
            // Add section header if new section (only when not filtering by section)
            if (!$sectionId && $currentSectionId !== $enrollment->section_id) {
                if ($currentSectionId !== null) {
                    $row++; // Add spacing between sections
                }
                $currentSectionId = $enrollment->section_id;
                $sectionName = $enrollment->section ? $enrollment->section->name : 'N/A';
                $sheet->setCellValue('A' . $row, 'Section: ' . $sectionName);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E2EFDA');
                $row++;
            }

            $student = $enrollment->student;
            if (!$student) continue;

            // Calculate age
            $age = $student->birthday ? abs($juneFirst->year - $student->birthday->year) : '';

            // Parse address
            $addressParts = $this->parseAddress($student->address ?? '');
            $parentGuardianParts = $this->parseName($student->parent_guardian_name ?? '');

            $sheet->setCellValue('A' . $row, $student->lrn ?? '');
            $sheet->setCellValue('B' . $row, $student->last_name ?? '');
            $sheet->setCellValue('C' . $row, $student->first_name ?? '');
            $sheet->setCellValue('D' . $row, $student->middle_name ?? '');
            $sheet->setCellValue('E' . $row, strtoupper(substr($student->gender ?? '', 0, 1)));
            $sheet->setCellValue('F' . $row, $student->birthday ? $student->birthday->format('m/d/Y') : '');
            $sheet->setCellValue('G' . $row, $age);
            $sheet->setCellValue('H' . $row, '');
            $sheet->setCellValue('I' . $row, '');
            $sheet->setCellValue('J' . $row, '');
            $sheet->setCellValue('K' . $row, $addressParts['house_street'] ?? '');
            $sheet->setCellValue('L' . $row, $addressParts['barangay'] ?? '');
            $sheet->setCellValue('M' . $row, $addressParts['municipality'] ?? '');
            $sheet->setCellValue('N' . $row, $addressParts['province'] ?? '');
            $sheet->setCellValue('O' . $row, '');
            $sheet->setCellValue('P' . $row, '');
            $sheet->setCellValue('Q' . $row, '');
            $sheet->setCellValue('R' . $row, '');
            $sheet->setCellValue('S' . $row, '');
            $sheet->setCellValue('T' . $row, '');
            $sheet->setCellValue('U' . $row, $parentGuardianParts['name'] ?? '');
            $sheet->setCellValue('V' . $row, $student->relationship_to_student ?? '');
            $sheet->setCellValue('W' . $row, $student->parent_guardian_phone ?? '');
            $sheet->setCellValue('X' . $row, '');

            // Count by gender
            if (strtolower($student->gender ?? '') === 'male') {
                $totalMale++;
            } elseif (strtolower($student->gender ?? '') === 'female') {
                $totalFemale++;
            }

            $row++;
        }

        // Apply borders to data area
        if ($enrollments->count() > 0) {
            $dataRange = 'A' . $headerRow . ':X' . ($row - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);
        }

        // Summary Table
        $row += 2;
        $summaryStartRow = $row;
        $sheet->setCellValue('A' . $row, 'REGISTERED');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('B' . $row, 'BoSY');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('C' . $row, 'EoSY');
        $sheet->getStyle('C' . $row)->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue('A' . $row, 'MALE');
        $sheet->setCellValue('B' . $row, $totalMale);
        $sheet->setCellValue('C' . $row, $totalMale);

        $row++;
        $sheet->setCellValue('A' . $row, 'FEMALE');
        $sheet->setCellValue('B' . $row, $totalFemale);
        $sheet->setCellValue('C' . $row, $totalFemale);

        $row++;
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('B' . $row, $totalMale + $totalFemale);
        $sheet->setCellValue('C' . $row, $totalMale + $totalFemale);

        // Apply styling to summary
        $summaryRange = 'A' . $summaryStartRow . ':C' . $row;
        $sheet->getStyle($summaryRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA']
            ]
        ]);

        $row++;
    }

    /**
     * Parse address string into components
     */
    private function parseAddress($address)
    {
        if (empty($address)) {
            return [
                'house_street' => '',
                'barangay' => '',
                'municipality' => '',
                'province' => ''
            ];
        }

        // Try to parse common address formats
        // This is a simple parser - can be enhanced based on actual address format
        $parts = explode(',', $address);
        $parts = array_map('trim', $parts);

        return [
            'house_street' => $parts[0] ?? '',
            'barangay' => $parts[1] ?? '',
            'municipality' => $parts[2] ?? '',
            'province' => $parts[3] ?? ''
        ];
    }

    /**
     * Parse name string into components
     */
    private function parseName($name)
    {
        if (empty($name)) {
            return ['name' => ''];
        }

        return ['name' => $name];
    }
}
