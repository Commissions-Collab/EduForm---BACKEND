<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\StudentBmi;
use App\Models\Section;
use App\Models\AcademicYear;
use App\Models\Quarter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class StudentBmiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'section_id' => 'required|exists:sections,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'quarter_id' => 'required|exists:quarters,id',
        ]);

        $sectionId = $request->section_id;
        $academicYearId = $request->academic_year_id;
        $quarterId = $request->quarter_id;

        // Step 1: Get all enrollments with user and student
        $enrollments = Enrollment::with('student')
            ->where('section_id', $sectionId)
            ->where('academic_year_id', $academicYearId)
            ->get();

        $studentIds = $enrollments->pluck('student.id')->toArray(); // Get actual student IDs

        // Step 2: Fetch all BMI records for these students in this academic year & quarter
        $bmiRecords = StudentBmi::whereIn('student_id', $studentIds)
            ->where('academic_year_id', $academicYearId)
            ->where('quarter_id', $quarterId)
            ->get()
            ->keyBy('student_id'); // index by student_id

        // Step 3: Map data
        $students = $enrollments->map(function ($enrollment) use ($bmiRecords) {
            $student = $enrollment->student;
            $bmi = $bmiRecords[$student->id] ?? null;

            return [
                'student_id' => $student->id,
                'name' => $student->fullName(),
                'height' => $bmi->height_cm ?? null,
                'weight' => $bmi->weight_kg ?? null,
                'bmi' => $bmi->bmi ?? null,
                'bmi_status' => $bmi->bmi_category ?? null,
                'bmi_record_id' => $bmi->id ?? null,
                'remarks' => $bmi->remarks ?? null,
            ];
        });

        return response()->json([
            'section_id' => $sectionId,
            'academic_year_id' => $academicYearId,
            'quarter_id' => $quarterId,
            'students' => $students,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'student_id' => ['required', 'exists:students,id'],
                'academic_year_id' => ['required', 'exists:academic_years,id'],
                'quarter_id' => ['required', 'exists:quarters,id'],
                'recorded_at' => ['nullable', 'date'],
                'height_cm' => ['required', 'numeric'],
                'weight_kg' => ['required', 'numeric'],
                'bmi' => ['nullable', 'numeric'],
                'bmi_category' => ['nullable', 'string'],
                'remarks' => ['nullable', 'string']
            ]);

            // Check for existing record
            $existingRecord = StudentBmi::where([
                'student_id' => $request->student_id,
                'academic_year_id' => $request->academic_year_id,
                'quarter_id' => $request->quarter_id,
            ])->first();

            if ($existingRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'BMI record already exists for this student in the selected academic year and quarter',
                ], 422);
            }

            $heightMeters = $request->height_cm / 100;
            $bmi = $request->bmi ?? round($request->weight_kg / ($heightMeters * $heightMeters), 2);

            $bmiCategory = $request->bmi_category ?? match (true) {
                $bmi < 18.5 => 'Underweight',
                $bmi >= 18.5 && $bmi < 24.9 => 'Normal',
                $bmi >= 25 && $bmi < 29.9 => 'Overweight',
                $bmi >= 30 => 'Obese',
                default => 'Unknown',
            };

            $bmiRecord = StudentBmi::create([
                'student_id' => $request->student_id,
                'academic_year_id' => $request->academic_year_id,
                'quarter_id' => $request->quarter_id,
                'recorded_at' => $request->recorded_at ?? now(),
                'height_cm' => $request->height_cm,
                'weight_kg' => $request->weight_kg,
                'bmi' => $bmi,
                'bmi_category' => $bmiCategory,
                'remarks' => $request->remarks,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'BMI record successfully created.',
                'data' => $bmiRecord,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create BMI for student',
                'error' => $e->getMessage()
            ], 500);
        }
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
        DB::beginTransaction();

        try {
            $studentBmi = StudentBmi::findOrFail($id);

            $request->validate([
                'student_id' => ['required', 'exists:students,id'],
                'academic_year_id' => ['required', 'exists:academic_years,id'],
                'quarter_id' => ['required', 'exists:quarters,id'],
                'recorded_at' => ['nullable', 'date'],
                'height_cm' => ['required', 'numeric'],
                'weight_kg' => ['required', 'numeric'],
                'bmi' => ['nullable', 'numeric'],
                'bmi_category' => ['nullable', 'string'],
                'remarks' => ['nullable', 'string']
            ]);

            $heightMeters = $request->height_cm / 100;
            $bmi = $request->bmi ?? round($request->weight_kg / ($heightMeters * $heightMeters), 2);

            $bmiCategory = $request->bmi_category ?? match (true) {
                $bmi < 18.5 => 'Underweight',
                $bmi >= 18.5 && $bmi < 24.9 => 'Normal',
                $bmi >= 25 && $bmi < 29.9 => 'Overweight',
                $bmi >= 30 => 'Obese',
                default => 'Unknown',
            };

            $studentBmi->update([
                'student_id' => $request->student_id,
                'academic_year_id' => $request->academic_year_id,
                'quarter_id' => $request->quarter_id,
                'recorded_at' => $request->recorded_at ?? now(),
                'height_cm' => $request->height_cm,
                'weight_kg' => $request->weight_kg,
                'bmi' => $bmi,
                'bmi_category' => $bmiCategory,
                'remarks' => $request->remarks,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'BMI record successfully updated.',
                'data' => $studentBmi,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update BMI record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $studentBmiRecord = StudentBmi::findOrFail($id);
            $studentBmiRecord->delete();

            return response()->json([
                'success' => true,
                'message' => 'Student BMI record deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student BMI record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export SF8 Excel - Learner's Basic Health and Nutrition Report
     */
    public function exportSF8Excel(Request $request)
    {
        try {
            $request->validate([
                'section_id' => 'required|exists:sections,id',
                'academic_year_id' => 'required|exists:academic_years,id',
                'quarter_id' => 'required|exists:quarters,id',
            ]);

            $sectionId = $request->section_id;
            $academicYearId = $request->academic_year_id;
            $quarterId = $request->quarter_id;

            $section = Section::with(['yearLevel', 'academicYear'])->findOrFail($sectionId);
            $academicYear = AcademicYear::findOrFail($academicYearId);
            $quarter = Quarter::findOrFail($quarterId);

            // Get all enrollments with students
            $enrollments = Enrollment::with(['student'])
                ->where('section_id', $sectionId)
                ->where('academic_year_id', $academicYearId)
                ->where('enrollment_status', 'enrolled')
                ->get();

            $studentIds = $enrollments->pluck('student.id')->toArray();

            // Fetch BMI records for this quarter
            $bmiRecords = StudentBmi::whereIn('student_id', $studentIds)
                ->where('academic_year_id', $academicYearId)
                ->where('quarter_id', $quarterId)
                ->get()
                ->keyBy('student_id');

            // Prepare student data with BMI
            $studentsData = [];
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student;
                if (!$student) continue;

                $bmi = $bmiRecords[$student->id] ?? null;
                $heightMeters = $bmi ? ($bmi->height_cm / 100) : null;
                $heightSquared = $heightMeters ? round($heightMeters * $heightMeters, 4) : null;

                // Calculate age
                $age = $student->birthday ? Carbon::parse($student->birthday)->age : null;

                // Map BMI category to SF8 categories
                $bmiCategory = $this->mapBmiCategoryToSF8($bmi ? $bmi->bmi_category : null, $bmi ? $bmi->bmi : null);

                $studentsData[] = [
                    'student' => $student,
                    'bmi' => $bmi,
                    'height_m' => $heightMeters ? round($heightMeters, 2) : null,
                    'height_squared' => $heightSquared,
                    'age' => $age,
                    'bmi_category_sf8' => $bmiCategory,
                ];
            }

            // Separate by gender
            $maleStudents = array_filter($studentsData, fn($s) => strtolower($s['student']->gender ?? '') === 'male');
            $femaleStudents = array_filter($studentsData, fn($s) => strtolower($s['student']->gender ?? '') === 'female');

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SF8 Health Report');

            // Set column widths
            $colWidths = [
                'A' => 6,  // No.
                'B' => 12, // LRN
                'C' => 25, // Learner's Name
                'D' => 12, // Birthdate
                'E' => 6,  // Age
                'F' => 10, // Weight (kg)
                'G' => 10, // Height (m)
                'H' => 10, // Height² (m²)
                'I' => 10, // BMI (kg/m²)
                'J' => 15, // BMI Category
                'K' => 15, // Height for Age (HFA)
                'L' => 20, // Remarks
            ];

            foreach ($colWidths as $col => $width) {
                $sheet->getColumnDimension($col)->setWidth($width);
            }

            // Header Section
            $row = 1;
            $sheet->setCellValue('A' . $row, 'Department of Education');
            $sheet->mergeCells('A' . $row . ':L' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $sheet->setCellValue('A' . $row, 'School Form 8 Learner\'s Basic Health and Nutrition Report (SF8)');
            $sheet->mergeCells('A' . $row . ':L' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $sheet->setCellValue('A' . $row, '(For All Grade Levels)');
            $sheet->mergeCells('A' . $row . ':L' . $row);
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
            $sheet->setCellValue('D' . $row, 'Grade:');
            $sheet->setCellValue('E' . $row, $section->yearLevel->name ?? '');

            $row++;
            $sheet->setCellValue('A' . $row, 'Section:');
            $sheet->setCellValue('B' . $row, $section->name);
            $sheet->setCellValue('D' . $row, 'Track/Strand (SHS):');
            $sheet->setCellValue('E' . $row, '');

            $row++;
            $sheet->setCellValue('A' . $row, 'School Year:');
            $sheet->setCellValue('B' . $row, $academicYear->name);
            $sheet->setCellValue('D' . $row, 'Quarter:');
            $sheet->setCellValue('E' . $row, $quarter->name ?? '');

            $row += 2;

            // Table Header
            $headerRow = $row;
            $sheet->setCellValue('A' . $row, 'No.');
            $sheet->setCellValue('B' . $row, 'LRN');
            $sheet->setCellValue('C' . $row, 'Learner\'s Name');
            $sheet->mergeCells('C' . $row . ':C' . ($row + 1));
            $sheet->setCellValue('D' . $row, 'Birthdate');
            $sheet->mergeCells('D' . $row . ':D' . ($row + 1));
            $sheet->setCellValue('E' . $row, 'Age');
            $sheet->mergeCells('E' . $row . ':E' . ($row + 1));
            $sheet->setCellValue('F' . $row, 'Weight');
            $sheet->mergeCells('F' . $row . ':F' . ($row + 1));
            $sheet->setCellValue('G' . $row, 'Height');
            $sheet->mergeCells('G' . $row . ':G' . ($row + 1));
            $sheet->setCellValue('H' . $row, 'Height²');
            $sheet->mergeCells('H' . $row . ':H' . ($row + 1));
            $sheet->setCellValue('I' . $row, 'Nutritional Status');
            $sheet->mergeCells('I' . $row . ':J' . $row);
            $sheet->setCellValue('K' . $row, 'Height for Age (HFA)');
            $sheet->mergeCells('K' . $row . ':K' . ($row + 1));
            $sheet->setCellValue('L' . $row, 'Remarks');
            $sheet->mergeCells('L' . $row . ':L' . ($row + 1));

            $row++;
            $sheet->setCellValue('C' . $row, '(Last Name, First Name, Name Extension, Middle Name)');
            $sheet->setCellValue('D' . $row, '(MM/DD/YYYY)');
            $sheet->setCellValue('F' . $row, '(kg)');
            $sheet->setCellValue('G' . $row, '(m)');
            $sheet->setCellValue('H' . $row, '(m²)');
            $sheet->setCellValue('I' . $row, 'BMI');
            $sheet->setCellValue('J' . $row, 'BMI Category');

            // Apply header styling
            $headerRange = 'A' . $headerRow . ':L' . $row;
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

            // MALE Section
            $maleStartRow = $row;
            $sheet->setCellValue('A' . $row, 'MALE');
            $sheet->mergeCells('A' . $row . ':L' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;

            $maleNo = 1;
            $maleSummary = [
                'severely_wasted' => 0,
                'wasted' => 0,
                'normal' => 0,
                'overweight' => 0,
                'obese' => 0,
                'severely_stunted' => 0,
                'stunted' => 0,
                'normal_hfa' => 0,
                'tall' => 0,
            ];

            foreach ($maleStudents as $data) {
                $student = $data['student'];
                $bmi = $data['bmi'];
                $bmiCategory = $data['bmi_category_sf8'];

                $sheet->setCellValue('A' . $row, $maleNo);
                $sheet->setCellValue('B' . $row, $student->lrn ?? '');
                $sheet->setCellValue('C' . $row, trim($student->last_name . ', ' . $student->first_name . ' ' . ($student->middle_name ?? '')));
                $sheet->setCellValue('D' . $row, $student->birthday ? $student->birthday->format('m/d/Y') : '');
                $sheet->setCellValue('E' . $row, $data['age'] ?? '');
                $sheet->setCellValue('F' . $row, $bmi ? round($bmi->weight_kg, 2) : '');
                $sheet->setCellValue('G' . $row, $data['height_m'] ?? '');
                $sheet->setCellValue('H' . $row, $data['height_squared'] ?? '');
                $sheet->setCellValue('I' . $row, $bmi ? round($bmi->bmi, 2) : '');
                $sheet->setCellValue('J' . $row, $bmiCategory);
                $sheet->setCellValue('K' . $row, ''); // Height for Age - not in database
                $sheet->setCellValue('L' . $row, $bmi ? $bmi->remarks : '');

                // Count for summary
                if ($bmiCategory) {
                    switch ($bmiCategory) {
                        case 'Severely Wasted':
                            $maleSummary['severely_wasted']++;
                            break;
                        case 'Wasted':
                            $maleSummary['wasted']++;
                            break;
                        case 'Normal':
                            $maleSummary['normal']++;
                            break;
                        case 'Overweight':
                            $maleSummary['overweight']++;
                            break;
                        case 'Obese':
                            $maleSummary['obese']++;
                            break;
                    }
                }

                $maleNo++;
                $row++;
            }

            // FEMALE Section
            $femaleStartRow = $row;
            $sheet->setCellValue('A' . $row, 'FEMALE');
            $sheet->mergeCells('A' . $row . ':L' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FCE4EC');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;

            $femaleNo = 1;
            $femaleSummary = [
                'severely_wasted' => 0,
                'wasted' => 0,
                'normal' => 0,
                'overweight' => 0,
                'obese' => 0,
                'severely_stunted' => 0,
                'stunted' => 0,
                'normal_hfa' => 0,
                'tall' => 0,
            ];

            foreach ($femaleStudents as $data) {
                $student = $data['student'];
                $bmi = $data['bmi'];
                $bmiCategory = $data['bmi_category_sf8'];

                $sheet->setCellValue('A' . $row, $femaleNo);
                $sheet->setCellValue('B' . $row, $student->lrn ?? '');
                $sheet->setCellValue('C' . $row, trim($student->last_name . ', ' . $student->first_name . ' ' . ($student->middle_name ?? '')));
                $sheet->setCellValue('D' . $row, $student->birthday ? $student->birthday->format('m/d/Y') : '');
                $sheet->setCellValue('E' . $row, $data['age'] ?? '');
                $sheet->setCellValue('F' . $row, $bmi ? round($bmi->weight_kg, 2) : '');
                $sheet->setCellValue('G' . $row, $data['height_m'] ?? '');
                $sheet->setCellValue('H' . $row, $data['height_squared'] ?? '');
                $sheet->setCellValue('I' . $row, $bmi ? round($bmi->bmi, 2) : '');
                $sheet->setCellValue('J' . $row, $bmiCategory);
                $sheet->setCellValue('K' . $row, ''); // Height for Age - not in database
                $sheet->setCellValue('L' . $row, $bmi ? $bmi->remarks : '');

                // Count for summary
                if ($bmiCategory) {
                    switch ($bmiCategory) {
                        case 'Severely Wasted':
                            $femaleSummary['severely_wasted']++;
                            break;
                        case 'Wasted':
                            $femaleSummary['wasted']++;
                            break;
                        case 'Normal':
                            $femaleSummary['normal']++;
                            break;
                        case 'Overweight':
                            $femaleSummary['overweight']++;
                            break;
                        case 'Obese':
                            $femaleSummary['obese']++;
                            break;
                    }
                }

                $femaleNo++;
                $row++;
            }

            // Apply borders to data area
            $dataRange = 'A' . $headerRow . ':L' . ($row - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

            // Summary Table
            $row += 2;
            $summaryStartRow = $row;
            $sheet->setCellValue('A' . $row, 'SUMMARY TABLE');
            $sheet->mergeCells('A' . $row . ':L' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;

            // Summary Header
            $sheet->setCellValue('A' . $row, 'SEX');
            $sheet->setCellValue('B' . $row, 'Nutritional Status');
            $sheet->mergeCells('B' . $row . ':G' . $row);
            $sheet->setCellValue('H' . $row, 'Height for Age (HFA)');
            $sheet->mergeCells('H' . $row . ':L' . $row);

            $row++;
            $sheet->setCellValue('B' . $row, 'Severely Wasted');
            $sheet->setCellValue('C' . $row, 'Wasted');
            $sheet->setCellValue('D' . $row, 'Normal');
            $sheet->setCellValue('E' . $row, 'Overweight');
            $sheet->setCellValue('F' . $row, 'Obese');
            $sheet->setCellValue('G' . $row, 'TOTAL');
            $sheet->setCellValue('H' . $row, 'Severely Stunted');
            $sheet->setCellValue('I' . $row, 'Stunted');
            $sheet->setCellValue('J' . $row, 'Normal');
            $sheet->setCellValue('K' . $row, 'Tall');
            $sheet->setCellValue('L' . $row, 'Total');

            // Apply header styling to summary
            $summaryHeaderRange = 'A' . ($summaryStartRow + 1) . ':L' . $row;
            $sheet->getStyle($summaryHeaderRange)->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
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
            // MALE Summary
            $maleTotal = $maleSummary['severely_wasted'] + $maleSummary['wasted'] + $maleSummary['normal'] + $maleSummary['overweight'] + $maleSummary['obese'];
            $maleHfaTotal = $maleSummary['severely_stunted'] + $maleSummary['stunted'] + $maleSummary['normal_hfa'] + $maleSummary['tall'];
            $sheet->setCellValue('A' . $row, 'MALE');
            $sheet->setCellValue('B' . $row, $maleSummary['severely_wasted']);
            $sheet->setCellValue('C' . $row, $maleSummary['wasted']);
            $sheet->setCellValue('D' . $row, $maleSummary['normal']);
            $sheet->setCellValue('E' . $row, $maleSummary['overweight']);
            $sheet->setCellValue('F' . $row, $maleSummary['obese']);
            $sheet->setCellValue('G' . $row, $maleTotal);
            $sheet->setCellValue('H' . $row, $maleSummary['severely_stunted']);
            $sheet->setCellValue('I' . $row, $maleSummary['stunted']);
            $sheet->setCellValue('J' . $row, $maleSummary['normal_hfa']);
            $sheet->setCellValue('K' . $row, $maleSummary['tall']);
            $sheet->setCellValue('L' . $row, $maleHfaTotal);

            $row++;
            // FEMALE Summary
            $femaleTotal = $femaleSummary['severely_wasted'] + $femaleSummary['wasted'] + $femaleSummary['normal'] + $femaleSummary['overweight'] + $femaleSummary['obese'];
            $femaleHfaTotal = $femaleSummary['severely_stunted'] + $femaleSummary['stunted'] + $femaleSummary['normal_hfa'] + $femaleSummary['tall'];
            $sheet->setCellValue('A' . $row, 'FEMALE');
            $sheet->setCellValue('B' . $row, $femaleSummary['severely_wasted']);
            $sheet->setCellValue('C' . $row, $femaleSummary['wasted']);
            $sheet->setCellValue('D' . $row, $femaleSummary['normal']);
            $sheet->setCellValue('E' . $row, $femaleSummary['overweight']);
            $sheet->setCellValue('F' . $row, $femaleSummary['obese']);
            $sheet->setCellValue('G' . $row, $femaleTotal);
            $sheet->setCellValue('H' . $row, $femaleSummary['severely_stunted']);
            $sheet->setCellValue('I' . $row, $femaleSummary['stunted']);
            $sheet->setCellValue('J' . $row, $femaleSummary['normal_hfa']);
            $sheet->setCellValue('K' . $row, $femaleSummary['tall']);
            $sheet->setCellValue('L' . $row, $femaleHfaTotal);

            $row++;
            // TOTAL Summary
            $grandTotal = $maleTotal + $femaleTotal;
            $grandHfaTotal = $maleHfaTotal + $femaleHfaTotal;
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->setCellValue('B' . $row, $maleSummary['severely_wasted'] + $femaleSummary['severely_wasted']);
            $sheet->setCellValue('C' . $row, $maleSummary['wasted'] + $femaleSummary['wasted']);
            $sheet->setCellValue('D' . $row, $maleSummary['normal'] + $femaleSummary['normal']);
            $sheet->setCellValue('E' . $row, $maleSummary['overweight'] + $femaleSummary['overweight']);
            $sheet->setCellValue('F' . $row, $maleSummary['obese'] + $femaleSummary['obese']);
            $sheet->setCellValue('G' . $row, $grandTotal);
            $sheet->setCellValue('H' . $row, $maleSummary['severely_stunted'] + $femaleSummary['severely_stunted']);
            $sheet->setCellValue('I' . $row, $maleSummary['stunted'] + $femaleSummary['stunted']);
            $sheet->setCellValue('J' . $row, $maleSummary['normal_hfa'] + $femaleSummary['normal_hfa']);
            $sheet->setCellValue('K' . $row, $maleSummary['tall'] + $femaleSummary['tall']);
            $sheet->setCellValue('L' . $row, $grandHfaTotal);

            // Apply borders and styling to summary
            $summaryRange = 'A' . ($summaryStartRow + 1) . ':L' . $row;
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

            // Footer - Assessment Details
            $row += 3;
            $sheet->setCellValue('A' . $row, 'Date of Assessment:');
            $sheet->setCellValue('B' . $row, $quarter->name ?? '');
            $row++;
            $sheet->setCellValue('A' . $row, 'Conducted/Assessed By:');
            $row++;
            $sheet->setCellValue('A' . $row, 'Certified Correct By:');
            $row++;
            $sheet->setCellValue('A' . $row, 'Reviewed By:');

            // Generate Excel file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'SF8_Health_Report_' . $section->name . '_' . $quarter->name . '_' . $academicYear->name . '.xlsx';

            ob_start();
            $writer->save('php://output');
            $content = ob_get_contents();
            ob_end_clean();

            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->header('Cache-Control', 'max-age=0');
        } catch (\Exception $e) {
            Log::error('SF8 Excel Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export SF8 Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map BMI category to SF8 categories
     */
    private function mapBmiCategoryToSF8($category, $bmiValue)
    {
        if (!$category && !$bmiValue) {
            return '';
        }

        // If we have BMI value, use it for more accurate mapping
        if ($bmiValue !== null) {
            if ($bmiValue < 16) {
                return 'Severely Wasted';
            } elseif ($bmiValue < 18.5) {
                return 'Wasted';
            } elseif ($bmiValue < 25) {
                return 'Normal';
            } elseif ($bmiValue < 30) {
                return 'Overweight';
            } else {
                return 'Obese';
            }
        }

        // Fallback to category mapping
        $categoryLower = strtolower($category ?? '');
        switch ($categoryLower) {
            case 'underweight':
                return 'Wasted';
            case 'normal':
                return 'Normal';
            case 'overweight':
                return 'Overweight';
            case 'obese':
                return 'Obese';
            default:
                return '';
        }
    }
}