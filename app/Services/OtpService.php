<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class OtpService
{
    private function hash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    private function otpKey(string $email): string
    {
        return 'otp_code:' . $this->hash($email);
    }

    private function pendingKey(string $email): string
    {
        return 'pending_registration:' . $this->hash($email);
    }

    public function createOtp(string $email): string
    {
        $otp = (string) random_int(100000, 999999);

        Cache::put(
            $this->otpKey($email),
            $otp,
            now()->addMinutes(5)
        );

        return $otp;
    }

    public function verifyOtp(string $email, string $otp): bool
    {
        $key = $this->otpKey($email);
        $stored = Cache::get($key);

        if (
            !is_string($stored) ||
            !hash_equals($stored, $otp)
        ) {
            return false;
        }

        // A successful OTP must not be reusable.
        Cache::forget($key);

        return true;
    }

    public function storePending(string $email, array $data): void
    {
        Cache::put(
            $this->pendingKey($email),
            $data,
            now()->addMinutes(30)
        );
    }

    public function getPending(string $email): ?array
    {
        return Cache::get($this->pendingKey($email));
    }

    public function hasPending(string $email): bool
    {
        return Cache::has($this->pendingKey($email));
    }

    public function forgetPending(string $email): void
    {
        Cache::forget($this->pendingKey($email));
    }
}
