<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForecastRejectedSummaryMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    /**
     * @param array<int, array{month:int, monthLabel:string, previousAmount:float, proposedAmount:float}> $changes
     */
    public function __construct(
        public string $submitterName,
        public string $rejectorName,
        public int    $clientId,
        public string $clientName,
        public int    $year,
        public array  $changes,
        /** Panorama de los 12 meses; ver ForecastYearOverviewService::build(). */
        public array  $overview = [],
    ) {
    }

    public function build(): self
    {
        $count  = count($this->changes);
        $suffix = $count === 1 ? '1 mes' : "{$count} meses";

        return $this->subject("Forecast rechazado — {$this->clientName} ({$suffix}, {$this->year})")
            ->view('emails.forecast_rejected_summary');
    }
}
