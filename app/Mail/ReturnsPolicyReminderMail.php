<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReturnsPolicyReminderMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public function __construct(
        public string $clientId,
        public string $clientName,
        public string $monthName,
        public int    $lastDigit,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Recordatorio: política anual de devoluciones — vence en ' . $this->monthName)
            ->view('emails.returns_policy_reminder');
    }
}
