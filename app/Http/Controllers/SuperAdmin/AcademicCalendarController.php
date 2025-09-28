<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicCalendarController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $calendar = AcademicCalendar::with('academicYear:id,name,is_current')
            ->orderBy('date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $calendar->items(),
            'current_page' => $calendar->currentPage(),
            'total_pages' => $calendar->lastPage(),
            'total' => $calendar->total(),
            'per_page' => $calendar->perPage()
        ]);
    }

    public function bulkStore(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'academic_year_id' => 'required|exists:academic_years,id',
                'entries' => 'required|array|min:1',
                'entries.*.date' => [
                    'required',
                    'date',
                    function ($attribute, $value) use ($request) {
                        $exists = AcademicCalendar::where('academic_year_id', $request->academic_year_id)
                            ->where('date', $value)
                            ->exists();
                        if ($exists) {
                            throw ValidationException::withMessages([
                                $attribute => "The date {$value} already exists for this academic year.",
                            ]);
                        }
                    }
                ],
                'entries.*.title' => [
                    'required',
                    'string',
                    'max:255',
                    function ($attribute, $value) use ($request) {
                        $exists = AcademicCalendar::where('academic_year_id', $request->academic_year_id)
                            ->where('title', $value)
                            ->exists();
                        if ($exists) {
                            throw ValidationException::withMessages([
                                $attribute => "The title '{$value}' already exists for this academic year.",
                            ]);
                        }
                    }
                ],
                'entries.*.type' => ['required', Rule::in(['regular', 'holiday', 'exam', 'no_class', 'special_event'])],
                'entries.*.description' => 'required|string|max:255',
                'entries.*.is_class_day' => 'required|boolean',
            ]);

            $academicYearId = $validated['academic_year_id'];
            $data = [];

            foreach ($validated['entries'] as $entry) {
                $data[] = [
                    'academic_year_id' => $academicYearId,
                    'date' => $entry['date'],
                    'type' => $entry['type'],
                    'title' => $entry['title'],
                    'description' => $entry['description'],
                    'is_class_day' => $entry['is_class_day'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            AcademicCalendar::insert($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Calendar entries created successfully.',
                'count' => count($data),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating calendar entries.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    // Show one calendar entry
    public function show(string $id)
    {
        $calendar = AcademicCalendar::with('academicYear')->findOrFail($id);

        return response()->json([
            'calendar' => $calendar
        ]);
    }

    // Update a calendar entry
    public function update(Request $request, $id)
    {
        try {
            $calendar = AcademicCalendar::find($id);
            if (!$calendar) {
                return response()->json(['message' => 'Calendar entry not found'], 404);
            }

            $validated = $request->validate([
                'academic_year_id' => 'sometimes|exists:academic_years,id',
                'date' => [
                    'sometimes',
                    'date',
                    Rule::unique('academic_calendars')->ignore($id)->where(function ($query) use ($request, $calendar) {
                        return $query->where('academic_year_id', $request->academic_year_id ?? $calendar->academic_year_id);
                    }),
                ],
                'type' => ['sometimes', Rule::in(['regular', 'holiday', 'exam', 'no_class', 'special_event'])],
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'is_class_day' => 'sometimes|boolean',
            ]);

            $calendar->update($validated);
            return response()->json([
                'message' => 'Calendar event updated successfully',
                'data' => $calendar
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating calendar entry.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    // Delete a calendar entry
    public function destroy(string $id)
    {
        try {
            $calendar = AcademicCalendar::findOrFail($id);

            $calendar->delete();

            return response()->json([
                'message' => 'Calendar event deleted successfully'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Server error.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getByYear(Request $request, $academic_year_id)
    {
        $perPage = $request->get('per_page', 10);

        $calendars = AcademicCalendar::where('academic_year_id', $academic_year_id)
            ->with('academicYear:id,name,is_current')
            ->orderBy('date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $calendars->items(),
            'current_page' => $calendars->currentPage(),
            'total_pages' => $calendars->lastPage(),
            'total' => $calendars->total(),
            'per_page' => $calendars->perPage()
        ]);
    }
}
