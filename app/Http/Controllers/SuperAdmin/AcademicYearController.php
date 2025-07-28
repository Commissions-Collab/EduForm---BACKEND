<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{ 
    // List all academic years
    public function index()
    {
         $academicYears = AcademicYear::all();

            return response()->json([
                'status' => 'success',
                'data' => $academicYears
            ]);
       
    }

    // Store new academic year
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:academic_years,name',
            'start_date' => 'required|date',
            'start_date' => 'required|date|after_or_equal:start_date',
            'is_current' => 'boolean'
        ]);

        // If is_current is true, reset others
        if ($request->is_current) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
        }

        $academicYear = AcademicYear::create($validated);
        return response()->json(['message' => 'Academic Year created successfully', 'data' => $academicYear]);
    }

    // Show single academic year
    public function show($id)
    {
        $year = AcademicYear::find($id);
        if (!$year) {
            return response()->json(['message' => 'Academic Year not found'], 404);
        }
        return response()->json($year);
    }

    // Update academic year
    public function update(Request $request, $id)
    {
        $year = AcademicYear::find($id);
        if (!$year) {
            return response()->json(['message' => 'Academic Year not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|unique:academic_years,name,' . $id,
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'is_current' => 'boolean'
        ]);

        if ($request->has('is_current') && $request->is_current) {
            AcademicYear::where('is_current', true)->where('id', '!=', $id)->update(['is_current' => false]);
        }

        $year->update($validated);
        return response()->json(['message' => 'Academic Year updated successfully', 'data' => $year]);
    }

    // Delete academic year
    public function destroy($id)
    {
        $year = AcademicYear::find($id);
        if (!$year) {
            return response()->json(['message' => 'Academic Year not found'], 404);
        }

        $year->delete();
        return response()->json(['message' => 'Academic Year deleted successfully']);
    }

    // Optional: Get current academic year
    public function current()
    {
        $current = AcademicYear::where('is_current', true)->first();
        if (!$current) {
            return response()->json(['message' => 'No current academic year set'], 404);
        }
        return response()->json($current);
    }
    
}
