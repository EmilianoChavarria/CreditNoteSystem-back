<?php

namespace App\Mail;

use App\Mail\Concerns\HasOverrideNotice;
use App\Models\EmailConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserBatchRegisteredMail extends Mailable
{
    use Queueable, SerializesModels, HasOverrideNotice;

    /**
     * @param array<int, array{fullName: string, email: string, password: string, roleName?: string, locale?: string}> $users
     */
    public function __construct(
        public array $users,
        public int $batchId,
        private string $mailLocale = 'es',
    ) {
        $this->mailLocale = $this->resolveLocale($users);
    }

    public function build(): self
    {
        app()->setLocale($this->mailLocale);

        return $this->subject(__('emails.batch_users_subject', ['id' => $this->batchId]))
            ->view('emails.user_batch_registered')
            ->with([
                'supportEmail' => (string) (EmailConfig::find(1)?->emailSupport ?? ''),
            ]);
    }

    /**
     * @param array<int, array{fullName: string, email: string, password: string, roleName?: string, locale?: string}> $users
     */
    private function resolveLocale(array $users): string
    {
        $locale = strtolower(trim((string) ($users[0]['locale'] ?? $this->mailLocale)));

        return in_array($locale, ['en', 'es'], true)
            ? $locale
            : (string) config('app.fallback_locale', 'es');
    }
}
