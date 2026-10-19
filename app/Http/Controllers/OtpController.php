<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class OtpController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * SEND (RESEND) OTP
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        $user = User::where('email', $email)->first();
        $pending = $this->otpService->hasPending($email);

        if (!$user && !$pending) {
            return response()->json([
                'message' => 'No pending registration found. Please sign up again.'
            ], 404);
        }

        if ($user && $user->is_verified) {
            return response()->json([
                'message' => 'User already verified'
            ], 409);
        }

        $key = 'otp:' . $email;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json([
                'message' => 'Please wait 60 seconds before resending OTP'
            ], 429);
        }

        RateLimiter::hit($key, 60);

        
try {
    $otp = $this->otpService->createOtp($email);

    // Send directly, without queueing the email.
    Mail::to($email)->send(new OtpMail($otp));

    return response()->json([
        'message' => 'OTP sent successfully',
    ]);
} catch (\Throwable $e) {
    \Log::error('OTP email delivery failed', [
        'email' => $email,
        'error' => $e->getMessage(),
    ]);

    return response()->json([
        'message' => 'Unable to send the verification code. Please try again.',
    ], 500);
}

    }

    /**
     * VERIFY OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
             'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $email = $request->email;

        $user = User::where('email', $email)->first();
        $pending = $this->otpService->getPending($email);

        if ($user && $user->is_verified) {
            return response()->json([
                'message' => 'User already verified'
            ], 409);
        }

        if (!$user && !$pending) {
            return response()->json([
                'message' => 'Registration expired. Please sign up again.'
            ], 404);
        }

        // Limit wrong guesses per email
        $attemptKey = 'otp-verify:' . $email;

        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return response()->json([
                'message' => 'Too many attempts. Please request a new code.'
            ], 429);
        }

        if (!$this->otpService->verifyOtp($email, (string) $request->otp)) {
            RateLimiter::hit($attemptKey, 300);

            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 422);
        }

        // OTP is valid: create the account now (or verify a legacy unverified user)
       
if (!$user) {
    if (
        !is_array($pending) ||
        empty($pending['name']) ||
        empty($pending['password'])
    ) {
        return response()->json([
            'message' => 'Registration details have expired. Please sign up again.',
        ], 404);
    }

    $user = User::create([
        'name' => $pending['name'],
        'email' => $email,
        'password' => $pending['password'],
        'is_verified' => true,
    ]);

    $this->otpService->forgetPending($email);
} else {
    $user->update([
        'is_verified' => true,
    ]);
}


        RateLimiter::clear('otp:' . $email);
        RateLimiter::clear($attemptKey);

        // Welcome email (a mail failure shouldn't fail the verification)
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Throwable $e) {
            \Log::error('Welcome email failed: ' . $e->getMessage());
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'OTP verified successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_verified' => true,
            ],
        ]);
    }
}