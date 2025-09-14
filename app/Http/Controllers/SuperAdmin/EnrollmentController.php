<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    // Get all enrollments
    public function index()
    {
        $enrollments = Enrollment::with([
            'student:id,lrn,first_name,middle_name,last_name,gender',
            'yearLevel:id,name',
            'section:id,name,year_level_id,academic_year_id',
            'academicYear:id,name'
        ])
            ->latest()
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $enrollments
        ]);
    }

    // public function students()
    // {
    //     $students = Student::select('id', 'lrn', 'first_name', 'middle_name', 'last_name', 'gender')
    //         ->orderBy('first_name')
    //         ->paginate(request('per_page', 1000));

    //     return response()->json([
    //         'success' => true,
    //         'data' => $students
    //     ]);
    // }

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
            'student_id.unique' => "Student ID {$request->student_id} is already enrolled in this academic year."
        ]);

        $enrollment = Enrollment::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student enrolled successfully.',
            'enrollment' => $enrollment
        ], 201);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|exists:students,id',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'grade_level' => 'required|integer|exists:year_levels,id',
            'section_id' => 'required|integer|exists:sections,id',
        ], [
            'student_ids.*.exists' => 'One or more students do not exist in the system.',
            'student_ids.required' => 'At least one student must be selected.',
            'student_ids.min' => 'At least one student must be selected.',
        ]);

        // Check for duplicate enrollments
        $alreadyEnrolledStudents = [];
        $enrollmentsToCreate = [];

        foreach ($validated['student_ids'] as $studentId) {
            $alreadyEnrolled = Enrollment::where('student_id', $studentId)
                ->where('academic_year_id', $validated['academic_year_id'])
                ->exists();

            if ($alreadyEnrolled) {
                $student = Student::find($studentId);
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

        // Return error if any students are already enrolled
        if (!empty($alreadyEnrolledStudents)) {
            return response()->json([
                'success' => false,
                'message' => 'The following students are already enrolled in this academic year: ' . implode(', ', $alreadyEnrolledStudents)
            ], 422);
        }

        // Return error if no students to enroll
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
                'message' => 'An error occurred while enrolling students. Please try again.'
            ], 500);
        }
    }

    // Show specific enrollment
    public function show(string $id)
    {
        $enrollment = Enrollment::with(['student', 'yearLevel', 'section', 'academicYear'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $enrollment
        ]);
    }

    // Update enrollment
    public function update(Request $request, $id)
    {
        $enrollment = Enrollment::findOrFail($id);

        $validated = $request->validate([
            'academic_year_id'   => 'sometimes|numeric',
            'grade_level'   => 'sometimes|exists:year_levels,id',
            'section_id'    => 'sometimes|exists:sections,id',
            'enrollment_status' => 'sometimes|string|in:enrolled,pending,withdrawn,transferred'
        ]);

        $enrollment->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment updated successfully',
            'data' => $enrollment
        ]);
    }

    // Delete enrollment
    public function destroy(string $id)
    {
        $enrollment = Enrollment::findOrFail($id);

        $enrollment->delete();

        return response()->json([
            'message' => 'Enrollment deleted successfully'
        ]);
    }

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
            // Check if student is already enrolled in the target academic year
            $alreadyEnrolled = Enrollment::where('student_id', $studentId)
                ->where('academic_year_id', $validated['next_academic_year_id'])
                ->exists();

            if ($alreadyEnrolled) {
                $student = Student::find($studentId);
                $studentName = $student ? "{$student->first_name} {$student->last_name}" : "ID {$studentId}";
                $errors[] = "{$studentName} is already enrolled in the target academic year.";
                continue;
            }

            // Get current enrollment for grade level validation
            $currentEnrollment = Enrollment::where('student_id', $studentId)
                ->with('yearLevel')
                ->latest()
                ->first();

            if ($currentEnrollment && $currentEnrollment->yearLevel) {
                $currentGradeNum = (int) filter_var($currentEnrollment->yearLevel->name, FILTER_SANITIZE_NUMBER_INT);
                $nextGradeLevel = \App\Models\YearLevel::find($validated['next_grade_level_id']);
                $nextGradeNum = (int) filter_var($nextGradeLevel->name, FILTER_SANITIZE_NUMBER_INT);

                // Allow promotion to next grade level or same grade level (for transfers/repeaters)
                if ($nextGradeNum < $currentGradeNum || $nextGradeNum > $currentGradeNum + 1) {
                    $student = Student::find($studentId);
                    $studentName = $student ? "{$student->first_name} {$student->last_name}" : "ID {$studentId}";
                    $errors[] = "{$studentName} cannot be promoted from grade {$currentGradeNum} to grade {$nextGradeNum}.";
                    continue;
                }
            }

            $promotionsToCreate[] = [
                'student_id' => $studentId,
                'academic_year_id' => $validated['next_academic_year_id'],
                'grade_level' => $validated['next_grade_level_id'], // Fixed: match database column
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
                'message' => 'An error occurred while promoting students. Please try again.'
            ], 500);
        }
    }
}
