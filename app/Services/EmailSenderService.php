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

        $context = [
            'class' => get_class($mailable),
            'to'    => [$recipientEmail],
            'cc'    => ($ccEmail !== null && $ccEmail !== $recipientEmail) ? [$ccEmail] : [],
            'bcc'   => [],
        ];

        Log::info('[EmailSender] intentando enviar correo', $context);

        try {
            $mailer->send($mailable);
            Log::info('[EmailSender] correo enviado exitosamente', $context);
        } catch (\Throwable $e) {
            Log::error('[EmailSender] fallo al enviar correo', $context + ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @param string|string[] $to
     * @param string[]        $cc
     * @param string[]        $bcc
     */
    public function sendWithCopies(Mailable $mailable, string|array $to, array $cc = [], array $bcc = []): void
    {
        $config = EmailConfig::find(1);
        $mode   = (string) ($config?->emailMode ?? 'normal');

        if ($mode === 'disabled') {
            return;
        }

        $originalTo = is_array($to) ? implode(', ', $to) : $to;

        if ($mode === 'override' && !empty($config?->overrideEmail)) {
            $hasMethod = method_exists($mailable, 'applyOverrideNotice');
            Log::info('[EmailSender] override mode (sendWithCopies)', [
                'class'     => get_class($mailable),
                'hasMethod' => $hasMethod,
                'original'  => $originalTo,
            ]);
            if ($hasMethod) {
                $mailable->applyOverrideNotice($originalTo);
            }
            Mail::to((string) $config->overrideEmail)->send($mailable);
            return;
        }

        $toList = is_array($to)
            ? array_values(array_filter($to, fn($e) => $e !== ''))
            : [$to];

        if (empty($toList)) {
            return;
        }

        $mailer = Mail::to($toList);

        $filteredCc = array_values(array_filter(array_unique($cc), fn($e) => $e !== ''));
        if (!empty($filteredCc)) {
            $mailer->cc($filteredCc);
        }

        $filteredBcc = array_values(array_filter(array_unique($bcc), fn($e) => $e !== ''));
        if (!empty($filteredBcc)) {
            $mailer->bcc($filteredBcc);
        }

        $context = [
            'class' => get_class($mailable),
            'to'    => $toList,
            'cc'    => $filteredCc,
            'bcc'   => $filteredBcc,
        ];

        Log::info('[EmailSender] intentando enviar correo (sendWithCopies)', $context);

        try {
            $mailer->send($mailable);
            Log::info('[EmailSender] correo enviado exitosamente (sendWithCopies)', $context);
        } catch (\Throwable $e) {
            Log::error('[EmailSender] fallo al enviar correo (sendWithCopies)', $context + ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
