<?php

namespace App\Http\Controllers;

use App\Jobs\SendOtpEmailJob;
use App\Mail\OtpMail;
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
     * SEND OTP
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if ($user->is_verified) {
            return response()->json([
                'message' => 'User already verified'
            ], 409);
        }

        $key = 'otp:' . $request->email;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json([
                'message' => 'Please wait 60 seconds before resendind OTP'
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $otpRecord = $this->otpService->createOtp($request->email);

        if (app()->environment('production')) {
            SendOtpEmailJob::dispatch(
                $request->email,
                $otpRecord->otp
            );
        } else {
            Mail::to($request->email)->send(new OtpMail($otpRecord->otp));
        }

        return response()->json([
            'message' => 'OTP sent successfully'
        ]);
    }

    /**
     * VERIFY OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if ($user->is_verified) {
            return response()->json([
                'message' => 'User already verified'
            ], 409);
        }

        $isValid = $this->otpService->verifyOtp(
            $request->email,
            $request->otp
        );

        if (!$isValid) {
            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 422);
        }

        $user->update([
            'is_verified' => true
        ]);

        // Clear rate limiter after success
        RateLimiter::clear('otp:' . $request->email);

        return response()->json([
            'message' => 'OTP verified successfully'
        ]);
    }
}