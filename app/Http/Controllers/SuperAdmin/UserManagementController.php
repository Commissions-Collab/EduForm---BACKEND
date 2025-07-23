<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use App\Models\Student;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    
    public function getStudentRecord()
    {
       $students = Student::all();

        return response()->json([
            'students' => $students
        ]);
    }

    public function updateStudent(Request $request,$id){
        $students = Student::find($id);

        if(!$students){
            return response()->json(['message' => 'student not found'], 404); 
        }

        $validated = $request -> validate([
            'LRN' =>  ['required','string','max:12',Rule::unique('students')->ignore($students->id),],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'birthday' => 'required|date',
            'gender' => ['required', Rule::enum(Gender::class)],
            'parents_fullname' => 'nullable|string|max:255',
            'relationship_to_student' => 'nullable|string|max:255',
            'parents_number' => 'nullable|string|max:15',
            'parents_email' => 'nullable|email',
            'image' => 'nullable|image|mimes:jpg,png,jpeg,tmp' // tmp is for testing only
        ]);

        $students -> update($validated);

        return response()->json([
        'message' => 'Student updated successfully',
        'student' => $students
    ]);

    }

    public function deleteStudent($id)
{
    $student = Student::find($id);

    if (!$student) {
        return response()->json(['message' => 'Student not found'], 404);
    }

    $student->delete();

    return response()->json([
        'message' => 'Student deleted successfully',
        'student' => $student
    ]);
}
}
