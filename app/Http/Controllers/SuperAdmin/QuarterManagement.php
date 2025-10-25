<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Quarter;
use Illuminate\Http\Request;

class QuarterManagement extends Controller
{
    public function index()
    {
        $quarters = Quarter::with(['academicYear:id,name,is_current'])
            ->select('id', 'academic_year_id', 'name', 'start_date', 'end_date')
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('name', 'asc')
            ->paginate(25);

        return response()->json([
            'quarters' => $quarters
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'quarters' => 'required|array|min:1|max:4',
            'quarters.*.start_date' => 'required|date',
            'quarters.*.end_date' => 'required|date|after_or_equal:quarters.*.start_date',
        ]);

        $academicYearId = $request->academic_year_id;

        // Predefined quarter names
        $quarterNames = ['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'];

        // Get how many quarters already exist for this academic year
        $existingCount = Quarter::where('academic_year_id', $academicYearId)->count();

        // Prevent more than 4 total quarters
        if ($existingCount + count($request->quarters) > 4) {
            return response()->json([
                'message' => 'You cannot have more than 4 quarters per academic year.'
            ], 422);
        }

        // Assign quarter names automatically based on existing count
        $quartersToInsert = [];
        foreach ($request->quarters as $index => $quarter) {
            $name = $quarterNames[$existingCount + $index] ?? null;
            if (!$name) break; // safety check

            $quartersToInsert[] = [
                'academic_year_id' => $academicYearId,
                'name' => $name,
                'start_date' => $quarter['start_date'],
                'end_date' => $quarter['end_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Quarter::insert($quartersToInsert);

        return response()->json([
            'message' => 'Quarters created successfully',
            'added' => count($quartersToInsert)
        ], 201);
    }



    public function show($id)
    {
        $quarter = Quarter::with(['academicYear:id,name,is_current'])->findOrFail($id);

        return response()->json([
            'quarter' => $quarter
        ]);
    }

    public function update(Request $request, $id)
    {
        $quarter = Quarter::findOrFail($id);

        $validatedData = $request->validate([
            'academic_year_id' => 'sometimes|required|exists:academic_years,id',
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
        ]);

        $quarter->update($validatedData);

        return response()->json([
            'message' => 'Quarter updated successfully',
            'quarter' => $quarter
        ]);
    }

    public function destroy($id)
    {
        $quarter = Quarter::findOrFail($id);
        $quarter->delete();

        return response()->json([
            'message' => 'Quarter deleted successfully'
        ]);
    }

    public function getByAcademicYear($academicYearId)
    {
        $quarters = Quarter::with(['academicYear:id,name'])
            ->where('academic_year_id', $academicYearId)
            ->orderBy('id')
            ->get(['id', 'academic_year_id', 'name', 'start_date', 'end_date']);

        return response()->json(['quarters' => $quarters]);
    }
}
