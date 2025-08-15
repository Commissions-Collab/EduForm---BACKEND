<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PhpParser\Node\Expr\FuncCall;

class UserController extends Controller
{

      public function indexTeacher()
    {
         $teacher = Teacher::all();

            return response()->json([
                'status' => 'success',
                'data' => $teacher
            ]);
       
    }

   public function createTeacher(Request $request){
    $validated = $request -> validate([
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'role'=> 'required|string|max:255',

        'employee_id' =>'required|string|max:255',
        'first_name' =>'required|string|max:255',
        'middle_name' =>'nullable|string|max:255',
        'last_name' => 'required|string|max:255',
        'phone' => 'nullable|string|max:255',
        'hire_date' =>'required|string|max:255',
        'employment_status' => 'required|string|max:255',
        
    ]);

    $user = User::create([
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'role' => 'teacher', 
    ]);

    $teacher = Teacher::create([
        'user_id' => $user->id,
        'first_name'=>$validated['first_name'],
        'middle_name'=>$validated['middle_name'],
        'last_name'=>$validated['last_name'],
        'employee_id'=>$validated['employee_id'],
        'phone'=>$validated['phone'],
        'hire_date'=>$validated['hire_date'],
        'employment_status'=> 'active',
        
    ]);
     return response()->json([
        'message' => 'Teacher registered successfully',
        'teacher' => $teacher,
        'user' => $user
    ], 201);
   }


   // delete teacher rec
   public function deleteTeacher(Request $request, $id){
    $teacher = Teacher::find($id);

    
    if (!$teacher){
        return response()->json(['message' => 'Teacher not found'], 404);
     }
    

    $teacher->delete();
    return response()->json(['message' => 'Teacher deleted successfully']);

   }
   

   public function updateTeacher(Request $request,$id){
     $teacher = Teacher::find($id);

    if (!$teacher){
        return response()->json(['message' => 'Teacher not found'], 404);
    }

    $validated = $request -> validate([
        'employee_id' =>'required|string|max:255',
        'first_name' =>'required|string|max:255',
        'middle_name' =>'nullable|string|max:255',
        'last_name' => 'required|string|max:255',
        'phone' => 'nullable|string|max:255',
        'hire_date' =>'required|string|max:255',
        'employment_status' => 'required|string|max:255',
    ]);

     $teacher->update([
        'first_name'=>$validated['first_name'],
        'middle_name'=>$validated['middle_name'],
        'last_name'=>$validated['last_name'],
        'employee_id'=>$validated['employee_id'],
        'phone'=>$validated['phone'],
        'hire_date'=>$validated['hire_date'],
        'employment_status'=> 'active',
    ]);

       return response()->json([
        'message' => 'Teacher updated successfully',
        'teacher' => $teacher
    ]);
   }

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
}
