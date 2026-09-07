<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForecastPendingApprovalSummaryMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    /**
     * @param array<int, array{month:int, monthLabel:string, previousAmount:float, proposedAmount:float}> $changes
     */
    public function __construct(
        public string $approverName,
        public string $submitterName,
        public int    $clientId,
        public string $clientName,
        public int    $year,
        public array  $changes,
    ) {
    }

    public function build(): self
    {
        $count  = count($this->changes);
        $suffix = $count === 1 ? '1 mes' : "{$count} meses";

        return $this->subject("Forecast pendiente de aprobación — {$this->clientName} ({$suffix}, {$this->year})")
            ->view('emails.forecast_pending_approval_summary');
    }
}
