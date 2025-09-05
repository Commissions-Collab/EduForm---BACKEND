<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    // Get all enrollments
    public function index()
    {
        $enrollments = Enrollment::with(['student:id,lrn,first_name,middle_name,last_name,gender', 'yearLevel:id,name', 'section:id,name,year_level_id,academic_year_id'])
            ->latest()
            ->paginate('25');

        return response()->json([
            'success' => 'true',
            'data' => $enrollments
        ]);
    }

    // Store new enrollment
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => [
                'required',
                'exists:students,id',
                Rule::unique('enrollments')->where(function ($query) use ($request) {
                    return $query->where('academic_year_id', $request->academic_year_id);
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
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade_level' => 'required|exists:year_levels,id',
            'section_id' => 'required|exists:sections,id',
            'status' => 'required|string|in:enrolled,pending,withdrawn,transferred',
        ], [
            'student_ids.*.exists' => 'One or more students do not exist in the system.',
        ]);

        $enrollments = [];

        foreach ($validated['student_ids'] as $studentId) {
            $alreadyEnrolled = Enrollment::where('student_id', $studentId)
                ->where('academic_year_id', $request->academic_year_id)
                ->exists();

            if ($alreadyEnrolled) {
                return response()->json([
                    'success' => false,
                    'message' => "Student ID {$studentId} is already enrolled in this academic year."
                ], 422);
            }

            $enrollments[] = [
                'student_id' => $studentId,
                'academic_year_id' => $validated['academic_year_id'],
                'grade_level' => $validated['grade_level'],
                'section_id' => $validated['section_id'],
                'enrollment_status' => $validated['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Enrollment::insert($enrollments);

        return response()->json([
            'success' => true,
            'message' => 'Students enrolled successfully.',
            'count' => count($enrollments)
        ]);
    }

    // Show specific enrollment
    public function show(string $id)
    {
        $enrollment = Enrollment::with(['student', 'yearLevel', 'section', 'academicYear'])->findOrFail($id);

        return response()->json($enrollment);
    }

    // Update enrollment
    public function update(Request $request, $id)
    {
        $enrollment = Enrollment::findOrFail($id);

        $validated = $request->validate([
            'academic_year_id'   => 'sometimes|numeric',
            'grade_level'   => 'sometimes|exists:year_levels,id',
            'section_id'    => 'sometimes|exists:sections,id',
            'enrollment_status' => 'sometimes|string'
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
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'next_academic_year_id' => 'required|exists:academic_years,id',
            'next_grade_level_id' => 'required|exists:year_levels,id',
            'section_id' => 'required|exists:sections,id',
        ]);

        $promotions = [];
        foreach ($validated['student_ids'] as $studentId) {
            $promotions[] = [
                'student_id' => $studentId,
                'academic_year_id' => $validated['next_academic_year_id'],
                'grade_level' => $validated['next_grade_level_id'],
                'section_id' => $validated['section_id'],
                'enrollment_status' => 'enrolled',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Enrollment::insert($promotions);

        return response()->json([
            'success' => true,
            'message' => 'Students promoted successfully.',
            'count' => count($promotions)
        ]);
    }
}
