<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    // create teachers Schedule in Schedule table
    public function createTeacherSchedule(Request $request){
    $validated = $request->validate([
        'teacher_id' => 'required|exists:teachers,id',
        'subject_id' => 'required|exists:subjects,id',
        'section_id' => 'required|exists:sections,id',
        'academic_year_id' => 'required|exists:academic_years,id',
        'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'room' => 'nullable|string|max:255',
    ]);

    // check for conflicts before inserting
     $conflict = Schedule::where([
        ['day_of_week', $validated['day_of_week']],
        ['start_time', $validated['start_time']],
        ['academic_year_id', $validated['academic_year_id']],

    ])->where(function ($query) use ($validated) {
        $query->where('section_id', $validated['section_id'])
              ->orWhere('teacher_id', $validated['teacher_id']);

        if (!empty($validated['room'])) {
            $query->orWhere('room', $validated['room']);
        }
    })->exists();

    if ($conflict) {
        return response()->json([
            'message' => 'Schedule conflict detected. Please choose a different time, teacher, section, or room.',
        ], 409); // Conflict HTTP status
    }

    $schedule = Schedule::create([
        'teacher_id' => $validated['teacher_id'],
        'subject_id' => $validated['subject_id'],
        'section_id' => $validated['section_id'],
        'academic_year_id' => $validated['academic_year_id'],
        'day_of_week' => $validated['day_of_week'],
        'start_time' => $validated['start_time'],
        'end_time' => $validated['end_time'],
        'room' => $validated['room'] ?? null,
        'is_active' => true,
    ]);

    return response()->json([
        'message' => 'Schedule created successfully.',
        'schedule' => $schedule,
    ], 201);
}

    public function upateTeacherSchedule(Request $request,$id){
        
    }

}
