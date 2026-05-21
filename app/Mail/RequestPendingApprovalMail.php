<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequestPendingApprovalMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public function __construct(
        public string $fullName,
        public string $requestNumber,
        public string $requestType,
        public string $classification,
    ) {}

    public function build(): self
    {
        return $this->subject('Tienes una solicitud pendiente de aprobación')
            ->view('emails.request_pending_approval');
    }
}
