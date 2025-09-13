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
        $sections = Section::with(['yearLevel:id,name', 'academicYear:id,name'])
            ->select('id', 'year_level_id', 'academic_year_id', 'name', 'strand', 'room', 'capacity')
            ->paginate('20');

        return response()->json([
            'success' => true,
            'sections' => $sections
        ]);
    }

    //create sections
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'year_level_id' => 'required|exists:year_levels,id',
                'academic_year_id' => 'required|exists:academic_years,id',
                'name' => 'required|string',
                'strand' => 'nullable|string',
                'room' => 'required|string',
                'capacity' => 'required|numeric',
            ]);

            $yearLevel = YearLevel::find($validated['year_level_id']);

            // Only allow strand if year level is G11 or G12
            if ($validated['strand'] && !in_array($yearLevel->code, ['G11', 'G12'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Strand is only applicable for G11 and G12 year levels.',
                ], 422);
            }

            Section::create([
                'year_level_id' => $validated['year_level_id'],
                'academic_year_id' => $validated['academic_year_id'],
                'name' => $validated['name'],
                'strand' => $validated['strand'],
                'room' => $validated['room'],
                'capacity' => $validated['capacity']
            ]);

            return response()->json([
                'message' => 'Section created successfully.',
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                "success" => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ]);
        }
    }


    // update section using id

    public function update(Request $request, string $id)
    {
        try {
            $section = Section::findOrFail($id);
            $validated = $request->validate([
                'year_level_id' => 'required|exists:year_levels,id',
                'academic_year_id' => 'required|exists:academic_years,id',
                'name' => 'required|string',
                'strand' => 'nullable|string',
                'room' => 'required|string',
                'capacity' => 'required|numeric',
            ]);

            $yearLevel = YearLevel::find($validated['year_level_id']);

            // Only allow strand if year level is G11 or G12
            if ($validated['strand'] && !in_array($yearLevel->code, ['G11', 'G12'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Strand is only applicable for G11 and G12 year levels.',
                ], 422);
            }

            $section->update([
                'year_level_id' => $validated['year_level_id'],
                'academic_year_id' => $validated['academic_year_id'],
                'name' => $validated['name'],
                'strand' => $validated['strand'],
                'room' => $validated['room'],
                'capacity' => $validated['capacity']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Section updated successfully.',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "success" => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ]);
        }
    }

    //delete section using id
    public function delete(string $id)
    {
        try {
            $section = Section::findOrFail($id);

            $section->delete();

            return response()->json([
                'message' => 'Section deleted successfully.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "success" => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ]);
        }
    }
}
