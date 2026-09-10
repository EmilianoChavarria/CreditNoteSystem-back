<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForecastFinalApprovedSummaryMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    /**
     * @param array<int, array{monthLabel:string, previousAmount:float, proposedAmount:float}> $changes
     */
    public function __construct(
        public string $clientName,
        public array  $changes,
        /** Panorama de los 12 meses; ver ForecastYearOverviewService::build(). */
        public array  $overview = [],
    ) {
    }

    public function build(): self
    {
        $count   = count($this->changes);
        $subject = $count === 1
            ? "Su objetivo de ventas fue actualizado — {$this->changes[0]['monthLabel']}"
            : "Su objetivo de ventas fue actualizado — {$count} períodos";

        return $this->subject($subject)
            ->view('emails.forecast_final_approved_summary');
    }
}
