<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Request as ModelsRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user.
     * This method handles the registration of a new user. Only students can register.
     * It creates a new user with the provided details and returns a JSON response with the user
     * @param \App\Http\Requests\RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        DB::beginTransaction();

        try {
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('student-images', 'public');
            }

            $requestUser = ModelsRequest::create([
                'request_to' => 2,
                'request_type' => 'student_signup',
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'LRN' => $data['LRN'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'birthday' => $data['birthday'],
                'gender' => $data['gender'],
                'parents_fullname' => $data['parents_fullname'] ?? null,
                'relationship_to_student' => $data['relationship_to_student'] ?? null,
                'parents_number' => $data['parents_number'] ?? null,
                'parents_email' => $data['parents_email'] ?? null,
                'image' => $imagePath, // Can be null
                'role' => 'student',
                'status' => 'pending'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Registration request submitted. Awaiting approval.',
                'request' => $requestUser,
            ], 202);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'error' => 'Registration failed',
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * This is not redirecting the user to their dashboard after login.
     * You can handle the redirection in your frontend after receiving the response.
     * @param \App\Http\Requests\LoginRequest $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */

    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = User::where('email', $request->input('email'))->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout the user and delete the access token.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful'
        ]);
    }
}
