<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;

class UserDataExportMail extends Mailable
{
    public string $userName;
    public string $jsonData;

    public function __construct(string $userName, string $jsonData)
    {
        $this->userName = $userName;
        $this->jsonData = $jsonData;
    }

    public function build()
    {
        return $this->subject('Your YossyVogue Data Export')
            ->view('emails.data-export')
            ->attachData($this->jsonData, 'my-data-export.json', [
                'mime' => 'application/json',
            ]);
    }
}
