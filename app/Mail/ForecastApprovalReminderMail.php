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
        public string $clientName = '',
    ) {
    }

    public function build(): self
    {
        $subject = $this->clientName !== ''
            ? "Recordatorio: forecast pendiente de tu aprobación — {$this->clientName}"
            : 'Recordatorio: forecasts pendientes de tu aprobación';

        return $this->subject($subject)
            ->view('emails.forecast_approval_reminder');
    }
}
