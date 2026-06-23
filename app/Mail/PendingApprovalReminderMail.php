<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PendingApprovalReminderMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    private string $mailLocale;

    /**
     * @param array<int, array{requestNumber: string, requestType: string, classification: string}> $requests
     */
    public function __construct(
        public string $fullName,
        public array $requests,
        string $locale = 'es',
    ) {
        $this->mailLocale = in_array(strtolower(trim($locale)), ['en', 'es'], true)
            ? strtolower(trim($locale))
            : 'es';
    }

    public function build(): self
    {
        app()->setLocale($this->mailLocale);

        return $this->subject(__('emails.reminder_subject'))
            ->view('emails.pending_approval_reminder');
    }
}
