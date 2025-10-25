<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\YearLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Year_levelsController extends Controller
{
    public function index()
    {
        $yearLevel = YearLevel::select(['id', 'name', 'code', 'sort_order', 'updated_at'])
            ->orderBy('sort_order')
            ->paginate(20);

        return response()->json($yearLevel);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255',
                'sort_order' => 'required|integer',
            ]);

            YearLevel::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'sort_order' => $validated['sort_order']
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Year Level created successfully',

            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                'message' => 'Error in creation of year level',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function update(string $id, Request $request)
    {
        DB::beginTransaction();

        $yearLevel = YearLevel::findOrFail($id);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255',
                'sort_order' => 'required|integer',
            ]);

            $yearLevel->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'sort_order' => $validated['sort_order']
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Year Level updated successfully',

            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                'message' => 'Error in updating of year level',
                'error' => $th->getMessage()
            ]);
        }
    }

    //Delete year level using id
    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $yearLvl = YearLevel::findOrFail($id);

            $yearLvl->delete();

            DB::commit();

            return response()->json([
                'message' => 'Year Level deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                'message' => 'Error in deleting of year level',
                'error' => $th->getMessage()
            ]);
        }
    }
}
