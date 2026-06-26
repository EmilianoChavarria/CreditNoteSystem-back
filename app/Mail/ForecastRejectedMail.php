<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForecastRejectedMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    public function __construct(
        public string $submitterName,
        public string $rejectorName,
        public int    $clientId,
        public string $clientName,
        public int    $month,
        public int    $year,
        public string $proposedAmount,
    ) {
    }

    public function build(): self
    {
        return $this->subject("Forecast rechazado — {$this->clientName} ({$this->month}/{$this->year})")
            ->view('emails.forecast_rejected');
    }
}
