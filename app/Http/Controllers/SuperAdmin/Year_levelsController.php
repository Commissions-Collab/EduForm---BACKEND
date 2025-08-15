<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\YearLevel;
use Illuminate\Http\Request;

class Year_levelsController extends Controller
{
    public function createYearLevel(Request $request){
        $valateded = $request -> validate([
            'name' =>'required|string|max:255',
            'code' => 'required|string|max:255',
            'sort_order'=> 'required|integer',
        ]);

        $year_level = YearLevel::create([
            'name' => $valateded['name'],
            'code' =>$valateded['code'],
            'sort_order' => '0'
        ]);

         return response()->json([
        'message' => 'Year Level created`` successfully',
        'year_level' => $year_level,
        
    ], 201);

    }

    //Delete year level using id
    public function deleteYearLevel(Request $request,$id){
        $yearLvl = YearLevel::find($id);

    if (!$yearLvl){
        return response()->json(['message' => 'Teacher not found'], 404);
     }
    
    $yearLvl->delete();
    return response()->json(['message' => 'Teacher deleted successfully']);

    }

    public function updateYeareLevel(Request $request, $id){
        $yearLevel = YearLevel::find($id);

        $valateded = $request-> validate([
            'name' =>'required|string|max:255',
            'code' => 'required|string|max:255',
            'sort_order'=> 'required|integer',
        ]);

        $yearLevel->update([
            'name' => $valateded['name'],
            'code' =>$valateded['code'],
            'sort_order' => '0'
        ]);
        return response()->json([
        'message' => 'Year Level updated successfully',
        'year_level' => $yearLevel,
        ]);
    }
}
