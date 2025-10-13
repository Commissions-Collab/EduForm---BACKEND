<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class StudentApprovalController extends Controller
{
    /**
     * Approve a student's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve(Request $request, $id)
    {
        try {
            $studentUser = User::where('id', $id)->where('role', 'student')->firstOrFail();

            // Here, you would typically update the user's status to approved
            $studentUser->status = 'approved';
            $studentUser->save();

            // Optionally, send an email to the student notifying them of the approval
            Mail::to($studentUser->email)->send(new \App\Mail\StudentApproved($studentUser));

            return response()->json(['message' => 'Student approved successfully.']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Student not found.'], 404);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Failed to approve student: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while approving the student.'], 500);
        }
    }

    /**
     * Reject a student's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        try {
            $studentUser = User::where('id', $id)->where('role', 'student')->firstOrFail();

            // Optionally, you might want to delete the user or mark them as rejected.
            // Here, we'll just send an email and then delete the user.
            
            // Send rejection email
            Mail::to($studentUser->email)->send(new \App\Mail\StudentRejected($studentUser));

            // Delete the user
            $studentUser->delete();

            return response()->json(['message' => 'Student rejected and removed successfully.']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Student not found.'], 404);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Failed to reject student: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while rejecting the student.'], 500);
        }
    }
}
