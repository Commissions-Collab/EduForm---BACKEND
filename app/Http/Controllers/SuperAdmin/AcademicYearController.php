<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AcademicCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AcademicYearController extends Controller
{
    // List all academic years
    public function index()
    {
        $academicYears = AcademicYear::select(['id', 'name', 'start_date', 'end_date', 'is_current', 'updated_at'])
            ->orderBy('is_current', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate('20');

        // Calculate school days for each academic year
        $academicYears->getCollection()->transform(function ($year) {
            $year->school_days_count = $this->calculateSchoolDays($year);
            $year->total_days = $this->calculateTotalDays($year);
            return $year;
        });

        return response()->json([
            'success' => true,
            'data' => $academicYears
        ]);
    }

    // Store new academic year
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:academic_years,name',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_current' => 'boolean'
            ]);

            // If is_current is true, reset others
            if ($request->is_current) {
                AcademicYear::where('is_current', true)->update(['is_current' => false]);
            }

            $academicYear = AcademicYear::create($validated);

            DB::commit();

            // Add school days count to response
            $academicYear->school_days_count = $this->calculateSchoolDays($academicYear);
            $academicYear->total_days = $this->calculateTotalDays($academicYear);

            return response()->json([
                'success' => true,
                'message' => 'Academic Year created successfully',
                'data' => $academicYear
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                'message' => 'Error in creation of academic year',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    // Update academic year
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $year = AcademicYear::find($id);
            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Year not found'
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|unique:academic_years,name,' . $id,
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_current' => 'boolean'
            ]);

            if ($request->has('is_current') && $request->is_current) {
                AcademicYear::where('is_current', true)
                    ->where('id', '!=', $id)
                    ->update(['is_current' => false]);
            }

            $year->update($validated);

            DB::commit();

            // Add school days count to response
            $year->school_days_count = $this->calculateSchoolDays($year);
            $year->total_days = $this->calculateTotalDays($year);

            return response()->json([
                'success' => true,
                'message' => 'Academic Year updated successfully',
                'data' => $year
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    // Delete academic year
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $year = AcademicYear::find($id);
            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Year not found'
                ], 404);
            }

            $year->delete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Academic Year deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                'message' => 'Server Error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate total days in academic year
     */
    private function calculateTotalDays($academicYear)
    {
        if (!$academicYear->start_date || !$academicYear->end_date) {
            return 0;
        }

        $start = Carbon::parse($academicYear->start_date);
        $end = Carbon::parse($academicYear->end_date);
        
        return $start->diffInDays($end) + 1;
    }

    /**
     * Calculate school days excluding weekends and holidays
     */
    private function calculateSchoolDays($academicYear)
    {
        if (!$academicYear->start_date || !$academicYear->end_date) {
            return 0;
        }

        $start = Carbon::parse($academicYear->start_date);
        $end = Carbon::parse($academicYear->end_date);
        
        // Get all holidays/no class days from academic calendar
        $nonClassDays = AcademicCalendar::where('academic_year_id', $academicYear->id)
            ->where('is_class_day', false)
            ->pluck('date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();

        $schoolDays = 0;
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            // Skip weekends (Saturday = 6, Sunday = 0)
            if ($date->dayOfWeek === Carbon::SATURDAY || $date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            // Skip holidays/no class days
            if (in_array($date->format('Y-m-d'), $nonClassDays)) {
                continue;
            }

            $schoolDays++;
        }

        return $schoolDays;
    }
}