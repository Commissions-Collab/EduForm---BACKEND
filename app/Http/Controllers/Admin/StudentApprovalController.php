<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationApprovedMail;
use App\Models\Request as ModelsRequest;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class StudentApprovalController extends Controller
{
    public function index()
    {
        $requests = ModelsRequest::where('status', 'approved')
            ->where('role', 'student')
            ->latest()
            ->paginate(10);

        return response()->json(['requests' => $requests]);
    }

    public function pending()
    {
        $requests = ModelsRequest::where('status', 'pending')
            ->where('role', 'student')
            ->latest()
            ->paginate(10);

        return response()->json(['requests' => $requests]);
    }

    public function rejected()
    {
        $requests = ModelsRequest::where('status', 'rejected')
            ->where('role', 'student')
            ->latest()
            ->paginate(10);

        return response()->json(['requests' => $requests]);
    }

    public function approvedStudents($id)
    {
        DB::beginTransaction();

        try {
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
                'first_name' => $studentRequest->first_name,
                'middle_name' => $studentRequest->middle_name,
                'last_name' => $studentRequest->last_name,
            ]);

            // Handle image upload
            $imagePath = null;
            if ($studentRequest->image) {
                $imagePath = $studentRequest->image; // If already stored, keep it
            }

            // Create student profile
            $student = Student::create([
                'user_id' => $user->id,
                'lrn' => $studentRequest->LRN,
                'student_id' => 'S' . rand(10000000, 99999999),
                'first_name' => $studentRequest->first_name,
                'middle_name' => $studentRequest->middle_name,
                'last_name' => $studentRequest->last_name,
                'birthday' => $studentRequest->birthday,
                'gender' => $studentRequest->gender,
                'parent_guardian_name' => $studentRequest->parents_fullname,
                'relationship_to_student' => $studentRequest->relationship_to_student,
                'parent_guardian_phone' => $studentRequest->parents_number,
                'parent_guardian_email' => $studentRequest->parents_email,
                'image' => $imagePath,
            ]);

            // Mark request as approved
            $studentRequest->status = 'approved';
            $studentRequest->save();

            // Send ONLY ONE approval email via Mail
            Mail::to($studentRequest->email)->send(new RegistrationApprovedMail($user));

            // Store notification in database only (no email)
            $user->notify(new AccountApprovedNotification($user));

            DB::commit();

            return response()->json([
                'message' => 'Student request approved successfully. Approval email sent.',
                'user' => $user,
                'student' => $student,
                'request' => $studentRequest
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'error' => 'Registration approval failed',
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function rejectApproval($id)
    {
        DB::beginTransaction();

        try {
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
                'error' => 'Rejection failed',
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
