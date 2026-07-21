<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForecastApprovalReminderMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    /**
     * @param array<int, array{clientId: int, clientName: string, month: int, year: int, proposedAmount: string, daysPending: int}> $items
     */
    public function __construct(
        public string $approverName,
        public array  $items,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Recordatorio: forecasts pendientes de tu aprobación')
            ->view('emails.forecast_approval_reminder');
    }
}
