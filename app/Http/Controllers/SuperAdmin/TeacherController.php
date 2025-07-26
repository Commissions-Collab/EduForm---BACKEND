<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PhpParser\Node\Expr\FuncCall;

class TeacherController extends Controller
{
   public function teacherRegistration(Request $request){
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


   public function updateTeacherRecord(Request $request,$id){
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
}
