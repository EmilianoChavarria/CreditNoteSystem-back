<?php

namespace App\Services;

use App\Models\EmailConfig;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailSenderService
{
    public function send(Mailable $mailable, string $recipientEmail, ?string $ccEmail = null): void
    {
        $config = EmailConfig::find(1);
        $mode = (string) ($config?->emailMode ?? 'normal');

        if ($mode === 'disabled') {
            return;
        }

        if ($mode === 'override' && !empty($config?->overrideEmail)) {
            $hasMethod = method_exists($mailable, 'applyOverrideNotice');
            Log::info('[EmailSender] override mode', [
                'class'     => get_class($mailable),
                'hasMethod' => $hasMethod,
                'original'  => $recipientEmail,
            ]);
            if ($hasMethod) {
                $mailable->applyOverrideNotice($recipientEmail);
            }
            Mail::to((string) $config->overrideEmail)->send($mailable);
            return;
        }

        $mailer = Mail::to($recipientEmail);

        if ($ccEmail !== null && $ccEmail !== $recipientEmail) {
            $mailer->cc($ccEmail);
        }

        $mailer->send($mailable);
    }
}
