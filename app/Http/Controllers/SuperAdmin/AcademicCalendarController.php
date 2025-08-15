<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicCalendarController extends Controller
{
   // List all calendar entries
    public function index()
    {
        return response()->json(AcademicCalendar::with('academicYear')->get());
    }

    // Store a new calendar entry
    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'date' => [
                'required',
                'date',
                Rule::unique('academic_calendars')->where(function ($query) use ($request) {
                    return $query->where('academic_year_id', $request->academic_year_id);
                }),
            ],
            'type' => ['required', Rule::in(['regular', 'holiday', 'exam', 'no_class', 'special_event'])],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_class_day' => 'required|boolean',
        ]);

        $calendar = AcademicCalendar::create($validated);
        return response()->json(['message' => 'Calendar entry created', 'data' => $calendar], 201);
    }

    // Show one calendar entry
    public function show($id)
    {
        $calendar = AcademicCalendar::with('academicYear')->find($id);
        if (!$calendar) {
            return response()->json(['message' => 'Calendar entry not found'], 404);
        }
        return response()->json($calendar);
    }

    // Update a calendar entry
    public function update(Request $request, $id)
    {
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
        return response()->json(['message' => 'Calendar entry updated', 'data' => $calendar]);
    }

    // Delete a calendar entry
    public function destroy($id)
    {
        $calendar = AcademicCalendar::find($id);
        if (!$calendar) {
            return response()->json(['message' => 'Calendar entry not found'], 404);
        }

        $calendar->delete();
        return response()->json(['message' => 'Calendar entry deleted']);
    }

    // Optional: List all events for a specific academic year
    public function getByYear($academic_year_id)
    {
        $events = AcademicCalendar::where('academic_year_id', $academic_year_id)->get();
        return response()->json($events);
    }
}
