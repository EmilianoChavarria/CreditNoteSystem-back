<?php

namespace App\Services;

use App\Models\EmailConfig;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailSenderService
{
    public function send(Mailable $mailable, string $recipientEmail): void
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
            $to = (string) $config->overrideEmail;
        } else {
            $to = $recipientEmail;
        }

        Mail::to($to)->send($mailable);
    }
}
