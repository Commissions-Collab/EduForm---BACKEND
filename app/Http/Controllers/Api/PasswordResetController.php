<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Notifications\ApiResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PasswordResetController extends Controller
{
    public function sendResetLink(ForgotPasswordRequest $request)
    {
        $email = $request->input('email');

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Create token using the broker's repository
        $token = Password::broker()->createToken($user);

        // Some brokers store hashed tokens in password_resets; ensure we insert/update
        $table = config('auth.passwords.'.config('auth.defaults.passwords').'.table', 'password_resets');
        $hashed = Hash::make($token);

        DB::table($table)->updateOrInsert(
            ['email' => $email],
            ['email' => $email, 'token' => $hashed, 'created_at' => now()]
        );

        // Send notification with token (frontend will receive token via email link)
        $user->notify(new ApiResetPasswordNotification($token));

        return response()->json(['message' => 'Reset link sent'], 200);
    }

    public function validateToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);
        $record = DB::table(config('auth.passwords.'.config('auth.defaults.passwords').'.table', 'password_resets'))
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json(['valid' => false], 422);
        }

        $valid = Hash::check($request->token, $record->token);

        if ($valid) {
            return response()->json(['valid' => true], 200);
        }

        return response()->json(['valid' => false], 422);
    }

    public function reset(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 200);
        }

        return response()->json(['message' => __($status)], 422);
    }
}
