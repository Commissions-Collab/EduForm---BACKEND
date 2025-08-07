<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
   //create sections
public function createSections (Request $request){
    $validated = $request -> validate([
        'year_level_id' => 'required|exists:year_levels,id',
        'name' => 'required|string',
        'academic_year_id' => 'required|exists:academic_year_id',
        'capacity' => 'required|string',
    ]);

    $section = Section :: create([
    'year_level_id' => $validated['year_level_id'],
    'academic_year_id' => $validated ['academic_year_id'],
    'name' => $validated['name'],
    'capacity' => '40'
    ]);

     return response()->json([
        'message' => 'Section created successfully.',
        'section' => $section,
    ], 201);
}


// update section using id

public function updateSection(Request $request, $id){
    $section = Section::find($id);

    if(!$section){
        return response()->json(['message' => 'Section not found'], 404);
    }
    
    $validated = $request -> validate([
        'year_level_id' => 'required|exists:year_levels,id',
        'name' => 'required|string',
        'academic_year_id' => 'required|exists:academic_year_id',
        'capacity' => 'required|string',
    ]);

    $section->update([
    'year_level_id' => $validated['year_level_id'],
    'academic_year_id' => $validated ['academic_year_id'],
    'name' => $validated['name'],
    'capacity' => '40'
    ]);

     return response()->json([
        'message' => 'Section Updated successfully.',
        'section' => $section,
    ], 201);
}

//delete section using id
public function deleteSection(Request $request, $id){
    $section = Section::find($id);

    if(!$section){
            return response()->json(['message' => 'Section not found'], 404);
        }

    $section->delete();

     return response()->json([
        'message' => 'Section deleted successfully.',
    ], 201);
}

}
