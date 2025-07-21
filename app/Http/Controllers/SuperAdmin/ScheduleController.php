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
        'year_level_id' => 'required|exists:year_levels,id',
        'day' => 'required|string',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
    ]);

    $schedule = Schedule::create([
        'teacher_id' => $validated['teacher_id'],
        'subject_id' => $validated['subject_id'],
        'section_id' => $validated['section_id'],
        'year_level_id' => $validated['year_level_id'],
        'day' => $validated['day'],
        'start_time' => $validated['start_time'],
        'end_time' => $validated['end_time'],
    ]);

    return response()->json([
        'message' => 'Schedule created successfully.',
        'schedule' => $schedule,
    ], 201);
}
}
