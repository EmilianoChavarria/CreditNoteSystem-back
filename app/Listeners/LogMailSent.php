<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class LogMailSent
{
    public function handle(MessageSent $event): void
    {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $serverIp = @file_get_contents('https://api.ipify.org', false, $ctx) ?: gethostbyname(gethostname());
        $requestIp = request()->ip();

        $to = array_map(fn ($addr) => $addr->getAddress(), $event->message->getTo() ?? []);

        Log::channel('mail_trace')->info('Mail sent', [
            'to'        => $to,
            'subject'   => $event->message->getSubject(),
            'server_ip' => $serverIp,
            'client_ip' => $requestIp,
            'tailscale' => str_starts_with((string) $serverIp, '100.'),
        ]);
    }
}
