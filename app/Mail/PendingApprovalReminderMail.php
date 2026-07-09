<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PendingApprovalReminderMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    private const SUBJECT_KEYS = [
        'approve'  => 'reminder_subject_approve',
        'reject'   => 'reminder_subject_reject',
        'sendBack' => 'reminder_subject_sendback',
    ];

    private string $mailLocale;

    /**
     * @param array<int, array{requestNumber: string, requestType: string, classification: string}> $requests
     * @param string $action one of 'approve', 'reject', 'sendBack' — controls the email subject
     */
    public function __construct(
        public string $fullName,
        public array $requests,
        string $locale = 'es',
        public string $action = 'approve',
    ) {
        $this->mailLocale = in_array(strtolower(trim($locale)), ['en', 'es'], true)
            ? strtolower(trim($locale))
            : 'es';
    }

    public function build(): self
    {
        app()->setLocale($this->mailLocale);

        $subjectKey = self::SUBJECT_KEYS[$this->action] ?? self::SUBJECT_KEYS['approve'];

        return $this->subject(__('emails.' . $subjectKey))
            ->view('emails.pending_approval_reminder');
    }
}
