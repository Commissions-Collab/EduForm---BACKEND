<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\YearLevel;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function index()
    {
        try {
            $sections = Section::with(['yearLevel:id,name', 'academicYear:id,name'])
                ->select('id', 'year_level_id', 'academic_year_id', 'name', 'strand', 'room', 'capacity')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $sections,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Error in SectionController@index', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching sections',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'year_level_id' => 'required|exists:year_levels,id',
                'academic_year_id' => 'required|exists:academic_years,id',
                'name' => 'required|string|max:255',
                'strand' => 'nullable|string|max:255',
                'room' => 'required|string|max:255',
                'capacity' => 'required|integer|min:1',
            ]);

            $section = Section::create([
                'year_level_id' => $validated['year_level_id'],
                'academic_year_id' => $validated['academic_year_id'],
                'name' => $validated['name'],
                'strand' => $validated['strand'] ?? null,
                'room' => $validated['room'],
                'capacity' => $validated['capacity'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Section created successfully.',
                'data' => $section,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            \Log::error('Error creating section', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $section = Section::findOrFail($id);

            $validated = $request->validate([
                'year_level_id' => 'required|exists:year_levels,id',
                'academic_year_id' => 'required|exists:academic_years,id',
                'name' => 'required|string|max:255',
                'strand' => 'nullable|string|max:255',
                'room' => 'required|string|max:255',
                'capacity' => 'required|integer|min:1',
            ]);

            $section->update([
                'year_level_id' => $validated['year_level_id'],
                'academic_year_id' => $validated['academic_year_id'],
                'name' => $validated['name'],
                'strand' => $validated['strand'] ?? null,
                'room' => $validated['room'],
                'capacity' => $validated['capacity'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Section updated successfully.',
            ], 200);
        } catch (\Throwable $th) {
            \Log::error('Error updating section', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function delete(string $id)
    {
        try {
            $section = Section::findOrFail($id);
            $section->delete();

            return response()->json([
                'success' => true,
                'message' => 'Section deleted successfully.',
            ]);
        } catch (\Throwable $th) {
            \Log::error('Error deleting section', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}