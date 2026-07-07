<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class WelcomeMail extends Mailable
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Welcome to YossyVogue - Your Fashion Destination!')
            ->view('emails.welcome')
            ->with([
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
            ]);
    }
}
