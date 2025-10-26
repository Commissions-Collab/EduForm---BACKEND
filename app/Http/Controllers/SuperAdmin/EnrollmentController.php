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
}
