<?php

namespace App\Providers;

use App\Listeners\LogMailSent;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Mail::extend('sendgrid', function (array $config) {
            return (new SendgridTransportFactory())->create(
                new Dsn('sendgrid+api', 'default', $config['key'])
            );
        });

        Event::listen(MessageSent::class, LogMailSent::class);
    }
}
