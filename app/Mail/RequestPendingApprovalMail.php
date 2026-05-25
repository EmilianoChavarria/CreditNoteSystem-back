<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequestPendingApprovalMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    private string $mailLocale;

    public function __construct(
        public string $fullName,
        public string $requestNumber,
        public string $requestType,
        public string $classification,
        string $locale = 'es',
    ) {
        $this->mailLocale = in_array(strtolower(trim($locale)), ['en', 'es'], true)
            ? strtolower(trim($locale))
            : 'es';
    }

    public function build(): self
    {
        app()->setLocale($this->mailLocale);

        return $this->subject(__('emails.pending_approval_subject'))
            ->view('emails.request_pending_approval');
    }
}
