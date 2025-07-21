<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
   public function teacherRegistration(Request $request){
    $validated = $request -> validate([
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'name' => 'required|string|max:255',
        'role'=> 'required|string|max:255',
        'is_advisor_id' => 'required|exists:sections,id',
        'subject_id' => 'required|exists:subjects,id'
    ]);


    $user = User::create([
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'role' => 'teacher', 
    ]);

    $teacher = Teacher::create([
        'user_id' => $user->id,
        'name'=>$validated['name'],
        'subject_id' => $validated['subject_id'],
        'is_advisor_id' => $validated['is_advisor_id'],
    ]);
     return response()->json([
        'message' => 'Teacher registered successfully',
        'teacher' => $teacher,
        'user' => $user
    ], 201);
   }
}
