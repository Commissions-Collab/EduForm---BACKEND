<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    
    public function getStudentRecord()
    {
        $students = User::where('role', UserRole::STUDENT->value)->get();

        return response()->json([
            'students' => $students
        ]);
    }

  
    public function createTeacherSchedule(Request $request)
{
    $validated = $request->validate([
        'teacher_id' => 'required|exists:teachers,id',
        'subject_id' => 'required|exists:subjects,id',
        'year_level_id' => 'required|exists:year_levels,id',
        'day' => 'required|string',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
    ]);

    $schedule = Schedule::create([
        'teacher_id' => $validated['teacher_id'],
        'subject_id' => $validated['subject_id'],
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

  
    public function store(Request $request)
    {
      
    }

   
    public function show(string $id)
    {
        
    }

    
    public function edit(string $id)
    {
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        
    }

  
    public function destroy(string $id)
    {
        
    }
}
