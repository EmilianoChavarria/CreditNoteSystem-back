<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForecastFinalApprovedMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public function __construct(
        public string $submitterName,
        public string $approverName,
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
        return $this->subject("Actualización de objetivo de ventas — {$this->month}/{$this->year}")
            ->view('emails.forecast_final_approved');
    }
}
