<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use App\Models\EmailConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public string $fullName;
    public string $email;
    public string $password;
    public bool $isTimkenUser;
    public string $supportEmail;
    private string $mailLocale;

    public function __construct(string $fullName, string $email, string $password, string $locale = 'es')
    {
        $this->fullName = $fullName;
        $this->email = $email;
        $this->password = $password;
        $this->mailLocale = $this->normalizeLocale($locale);
        $this->isTimkenUser = str_ends_with(strtolower($email), '@timken.com');
        $this->supportEmail = (string) (EmailConfig::find(1)?->emailSupport ?? '');
    }

    public function build(): self
    { 
        app()->setLocale($this->mailLocale);

        return $this->subject(__('emails.subject'))
            ->view('emails.user_registered');
    }   

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return in_array($locale, ['en', 'es'], true)
            ? $locale
            : (string) config('app.fallback_locale', 'es');
    }
}
