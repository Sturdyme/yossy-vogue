<?php

namespace App\Services;

use App\Models\OtpCode;

class OtpService
{
    public function generateOtp(): int
    {
        return rand(100000, 999999);
    }

    public function createOtp(string $email)
    {
        OtpCode::where('email', $email)->delete();

        return OtpCode::updateOrCreate(
            ['email' => $email],
            [
                'otp' => $this->generateOtp(),
                'expires_at' => now()->addMinutes(5),
                'is_used' => false,
            ]
        );
    }

    public function verifyOtp(string $email, string $otp): bool
    {
        $record = OtpCode::where('email', $email)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->first();

        if (!$record) {
            return false;
        }

        if (now()->greaterThan($record->expires_at)) {
            return false;
        }

        $record->update(['is_used' => true]);

        return true;
    }
}
