<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Request as ModelsRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentApprovalController extends Controller
{
    public function index()
    {
        $requests = ModelsRequest::where('status', 'approved')
            ->where('role', 'student')
            ->latest()
            ->paginate(10);

        return response()->json([
            'requests' => $requests
        ]);
    }

    public function pending()
    {
        $requests = ModelsRequest::where('status', 'pending')
            ->where('role', 'student')
            ->latest()
            ->paginate(10);

        return response()->json([
            'requests' => $requests
        ]);
    }

    public function rejected()
    {
        $requests = ModelsRequest::where('status', 'rejected')
            ->where('role', 'student')
            ->latest()
            ->paginate(10);

        return response()->json([
            'requests' => $requests
        ]);
    }

    /** @var \App\Models\Request $studentRequest 
     * 
    */
    public function approvedStudents($id)
    {
        DB::beginTransaction();

        try {
            /** 
             * @var \App\Models\Request $studentRequest 
             */
            $studentRequest = ModelsRequest::find($id);

            if (!$studentRequest) {
                return response()->json(['message' => 'Request not found'], 404);
            }

            // Check if already approved
            if ($studentRequest->status === 'approved') {
                return response()->json(['message' => 'Request already approved'], 400);
            }

            // Create user
            $user = User::create([
                'email' => $studentRequest->email,
                'password' => $studentRequest->password,
                'role' => 'student',
            ]);

            // Create student profile
            $student = Student::create([
                'user_id' => $user->id,
                'LRN' => $studentRequest->LRN,
                'first_name' => $studentRequest->first_name,
                'middle_name' => $studentRequest->middle_name,
                'last_name' => $studentRequest->last_name,
                'birthday' => $studentRequest->birthday,
                'gender' => $studentRequest->gender,
                'parents_fullname' => $studentRequest->parents_fullname,
                'relationship_to_student' => $studentRequest->relationship_to_student,
                'parents_number' => $studentRequest->parents_number,
                'parents_email' => $studentRequest->parents_email,
                'image' => $studentRequest->image,
            ]);

            // Mark request as approved
            $studentRequest->status = 'approved';
            $studentRequest->save();

            DB::commit();

            return response()->json([
                'message' => 'Student request approved successfully',
                'user' => $user,
                'student' => $student,
                'request' => $studentRequest
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'error' => 'Registration failed',
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function rejectApproval($id) {
        DB::beginTransaction();

        try {
            /** 
             * @var \App\Models\Request $studentRequest 
             */

            $studentRequest = ModelsRequest::find($id);

            if (!$studentRequest) {
                return response()->json(['message' => 'Request not found'], 404);
            }

            // Check if already rejected
            if ($studentRequest->status === 'rejected') {
                return response()->json(['message' => 'Request already rejected'], 400);
            }

            // Mark request as rejected
            $studentRequest->status = 'rejected';
            $studentRequest->save();

            DB::commit();

            return response()->json([
                'message' => 'Student request rejected successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'error' => 'Registration failed',
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
