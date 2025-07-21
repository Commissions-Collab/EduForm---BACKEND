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
    ]);

    $section = Section :: create([
    'year_level_id' => $validated['year_level_id'],
    'name' => $validated['name']
    ]);

     return response()->json([
        'message' => 'Section created successfully.',
        'section' => $section,
    ], 201);
}
}
