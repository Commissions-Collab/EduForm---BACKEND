<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\StudentBmi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
}