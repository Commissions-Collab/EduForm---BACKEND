<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
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

    public function destroy(string $id)
    {
        
    }
}
