<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class OtpMail extends Mailable
{
    public string $otp;

    public function __construct(string $otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Your YossyVogue OTP Code')
            ->view('otp');
    }
}