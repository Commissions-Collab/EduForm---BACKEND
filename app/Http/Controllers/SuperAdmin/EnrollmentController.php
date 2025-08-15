<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
     // Get all enrollments
    public function index()
    {
        $enrollments = Enrollment::with(['student', 'yearLevel', 'section'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $enrollments
        ]);
    }

    // Store new enrollment
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'    => 'required|exists:users,id',
            'school_year'   => 'required|string',
            'grade_level'   => 'required|exists:year_levels,id',
            'section_id'    => 'required|exists:sections,id',
        ]);

        $enrollment = Enrollment::create($validated);

        return response()->json([
            'message' => 'Enrollment created successfully',
            'data' => $enrollment
        ]);
    }

    // Show specific enrollment
    public function show($id)
    {
        $enrollment = Enrollment::with(['student', 'yearLevel', 'section'])->find($id);

        if (!$enrollment) {
            return response()->json(['message' => 'Enrollment not found'], 404);
        }

        return response()->json($enrollment);
    }

    // Update enrollment
    public function update(Request $request, $id)
    {
        $enrollment = Enrollment::find($id);

        if (!$enrollment) {
            return response()->json(['message' => 'Enrollment not found'], 404);
        }

        $validated = $request->validate([
            'student_id'    => 'sometimes|exists:users,id',
            'school_year'   => 'sometimes|string',
            'grade_level'   => 'sometimes|exists:year_levels,id',
            'section_id'    => 'sometimes|exists:sections,id',
        ]);

        $enrollment->update($validated);

        return response()->json([
            'message' => 'Enrollment updated successfully',
            'data' => $enrollment
        ]);
    }

    // Delete enrollment
    public function destroy($id)
    {
        $enrollment = Enrollment::find($id);

        if (!$enrollment) {
            return response()->json(['message' => 'Enrollment not found'], 404);
        }

        $enrollment->delete();

        return response()->json(['message' => 'Enrollment deleted successfully']);
    }
}
