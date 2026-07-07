<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOtpEmailJob;
use App\Models\User;
use App\Notifications\SendOTPNotification;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Http\Request;

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

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'is_verified' => false,
    ]);

     $otpRecord = $otpService->createOtp($user->email);

     SendOtpEmailJob::dispatch(
        $user->email,
        $otpRecord->otp
     );

    $token = $user->createToken('auth_token')->plainTextToken;

   


    return response()->json([
        'message' => 'Account created. Please verify OTP sent to your email.',
        "token" => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_verified' => false,
        ]
    
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

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
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

}