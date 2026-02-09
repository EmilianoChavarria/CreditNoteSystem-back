<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $fullName;
    public string $email;
    public string $password;

    public function __construct(string $fullName, string $email, string $password)
    {
        $this->fullName = $fullName;
        $this->email = $email;
        $this->password = $password;
    }

    public function build(): self
    {
        return $this->subject('Bienvenido a la plataforma')
            ->view('emails.user_registered');
    }
}
