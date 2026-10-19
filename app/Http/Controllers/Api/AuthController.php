<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Log;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

 public function register(Request $request, OtpService $otpService)
{
    $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|confirmed|min:6',
    ]);

    $otpService->storePending($request->email, [
        'name' => $request->name,
        'password' => bcrypt($request->password),
    ]);

    $otp = $otpService->createOtp($request->email);

    try {
        Mail::to($request->email)->send(new OtpMail($otp));
    } catch (\Throwable $e) {
        \Log::error('Register OTP email failed: ' . $e->getMessage());

        return response()->json([
            'message' => 'We could not send your verification code. Please try again.'

        ], 500);
    }

    return response()->json([
        'message' => 'Please verify the OTP sent to your email to complete registration.',
        'email' => $request->email,
        'requires_verification' => true,
    ]);
}

 public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !\Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Invalid email or password'
        ], 401);
    }

    if (!$user->is_verified) {
        return response()->json([
            'message' => 'Please verify your email with the OTP nefore logging in.',
            'requires_verification' => true,
            'emaile' => $user->email,
        ], 403);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_verified' => (bool) $user->is_verified,
        ],
        'token' => $token
    ]);
}

       public function logout(Request $request) 
       {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
       }

       public function logoutAll(Request $request)
       {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out of all devices successfully',
        ]);
       }

}