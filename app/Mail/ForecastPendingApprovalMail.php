<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForecastPendingApprovalMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public function __construct(
        public string $approverName,
        public string $submitterName,
        public int    $clientId,
        public string $clientName,
        public int    $month,
        public int    $year,
        public string $proposedAmount,
        public string $previousAmount,
    ) {
    }

    public function build(): self
    {
        return $this->subject("Forecast pendiente de aprobación — {$this->clientName} ({$this->month}/{$this->year})")
            ->view('emails.forecast_pending_approval');
    }
}
